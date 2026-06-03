<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Controller\Router;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Generator\JsonlGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsFullTxtGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsTxtGenerator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Serves the four AI-discovery files at the storefront, with proper MIME types,
 * Cache-Control + ETag + Last-Modified, and 404 short-circuits.
 *
 * @since 3.0.0
 */
class Index implements ActionInterface, HttpGetActionInterface
{
    private const MIME = [
        'llms.txt'        => 'text/plain; charset=utf-8',
        'llms-full.txt'   => 'text/plain; charset=utf-8',
        'llms.jsonl'      => 'application/x-ndjson; charset=utf-8',
        'llms-full.jsonl' => 'application/x-ndjson; charset=utf-8',
    ];

    public function __construct(
        private readonly HttpResponse $response,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly LlmsTxtGenerator $llmsTxtGenerator,
        private readonly LlmsFullTxtGenerator $llmsFullTxtGenerator,
        private readonly JsonlGenerator $jsonlGenerator,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();
            $file  = (string) $this->request->getParam(Router::PARAM_FILE, 'llms.txt');

            if (!$this->config->isEnabled($store) || $this->config->isStoreExcluded($store)) {
                return $this->notFound("Not available.\n");
            }
            if (method_exists($store, 'isActive') && !$store->isActive()) {
                return $this->notFound("Not available.\n");
            }
            if (!isset(self::MIME[$file])) {
                return $this->notFound("Not found.\n");
            }

            $absolutePath = $this->resolveFilePath($file, $store->getCode());
            if ($absolutePath === null || !is_file($absolutePath)) {
                return $this->notFound("Not generated yet.\n");
            }

            // Per-spec mime, cache headers, ETag, conditional GET.
            $mtime = (int) filemtime($absolutePath);
            $size  = (int) filesize($absolutePath);
            $etag  = sprintf('"%s-%x-%x"', substr(hash('sha256', $absolutePath), 0, 8), $mtime, $size);

            $ifNoneMatch = $this->request->getHeader('If-None-Match');
            $ifModifiedSince = $this->request->getHeader('If-Modified-Since');
            if (
                ($ifNoneMatch && trim((string) $ifNoneMatch) === $etag)
                || ($ifModifiedSince && strtotime((string) $ifModifiedSince) >= $mtime)
            ) {
                $this->response->setHttpResponseCode(304);
                $this->response->setHeader('ETag', $etag, true);
                $this->response->setHeader('Cache-Control', sprintf(
                    'public, max-age=%d',
                    $this->config->getHttpCacheTtl()
                ), true);
                return $this->response;
            }

            // Stream the file body — never load into memory.
            $content = $this->readFile($absolutePath);
            $result = $this->resultRawFactory->create();
            $result->setHeader('Content-Type', self::MIME[$file], true);
            $result->setHeader('Cache-Control', sprintf(
                'public, max-age=%d',
                $this->config->getHttpCacheTtl()
            ), true);
            $result->setHeader('ETag', $etag, true);
            $result->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true);
            $result->setHeader('X-Content-Type-Options', 'nosniff', true);
            $result->setHeader('X-Robots-Tag', 'noindex, follow', true);
            $result->setContents($content);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] Controller error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->response->setHttpResponseCode(500);
            $this->response->setBody("Internal error.\n");
            return $this->response;
        }
    }

    /**
     * Map URL → on-disk generated file path.
     */
    private function resolveFilePath(string $file, string $storeCode): ?string
    {
        return match ($file) {
            'llms.txt'        => $this->llmsTxtGenerator->getFilePath($storeCode),
            'llms-full.txt'   => $this->llmsFullTxtGenerator->getFilePath($storeCode),
            'llms.jsonl', 'llms-full.jsonl' => $this->jsonlGenerator->getFilePath($storeCode),
            default => null,
        };
    }

    /**
     * Read file via the Filesystem abstraction (so it works under tests too).
     */
    private function readFile(string $absolutePath): string
    {
        // For files up to ~100MB this is fine; larger should be served by Nginx directly.
        return (string) file_get_contents($absolutePath);
    }

    private function notFound(string $body): HttpResponse
    {
        $this->response->setHttpResponseCode(404);
        $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->response->setBody($body);
        return $this->response;
    }
}
