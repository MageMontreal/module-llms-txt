<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Controller\Router;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

/**
 * Serves the per-entity Markdown mirror requested by `/{url_key}.md`.
 *
 * For every product/category/CMS page rewrite in the store, the corresponding
 * `.md` URL serves a clean markdown rendering of the entity (no theme, no
 * navigation, no checkout chrome) suitable for LLM ingestion.
 *
 * Spec reference: llmstxt.org recommends pages with useful LLM content expose
 * a markdown version at the same URL with `.md` appended.
 *
 * @since 3.0.0
 */
class MdMirror implements ActionInterface, HttpGetActionInterface
{
    public function __construct(
        private readonly HttpResponse $response,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly UrlFinderInterface $urlFinder,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly SanitizerInterface $sanitizer,
        private readonly OutputContextFactory $contextFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();

            if (
                !$this->config->isEnabled($store)
                || $this->config->isStoreExcluded($store)
                || !$this->config->isMdMirrorEnabled($store)
            ) {
                return $this->notFound();
            }

            $path = (string) $this->request->getParam(Router::PARAM_MD_PATH);
            if ($path === '' || !str_ends_with($path, '.md')) {
                return $this->notFound();
            }

            // Strip .md suffix and look up the rewrite.
            $requestPath = substr($path, 0, -3);
            $requestPath = ltrim($requestPath, '/');

            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::REQUEST_PATH => $requestPath,
                UrlRewrite::STORE_ID     => (int) $store->getId(),
            ]);
            if ($rewrite === null) {
                // Try with a trailing slash; some Magento setups index that way.
                $rewrite = $this->urlFinder->findOneByData([
                    UrlRewrite::REQUEST_PATH => $requestPath . '.html',
                    UrlRewrite::STORE_ID     => (int) $store->getId(),
                ]);
            }
            if ($rewrite === null || $rewrite->getRedirectType() !== 0) {
                return $this->notFound();
            }

            $context = $this->contextFactory->create(
                $store,
                OutputContextInterface::FORMAT_LLMS_FULL_TXT,
                OutputContextInterface::VERBOSITY_FULL
            );

            $markdown = $this->renderEntity($rewrite, $context);
            if ($markdown === null) {
                return $this->notFound();
            }

            $result = $this->resultRawFactory->create();
            $result->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
            $result->setHeader('Cache-Control', sprintf(
                'public, max-age=%d',
                $this->config->getHttpCacheTtl()
            ), true);
            $result->setHeader('X-Robots-Tag', 'noindex, follow', true);
            $result->setContents($markdown);
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] MdMirror error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return $this->notFound();
        }
    }

    private function renderEntity(UrlRewrite $rewrite, OutputContextInterface $context): ?string
    {
        $entityType = $rewrite->getEntityType();
        $entityId   = (int) $rewrite->getEntityId();
        $storeId    = (int) $context->getStore()->getId();

        return match ($entityType) {
            'product'  => $this->renderProduct($entityId, $storeId, $context),
            'category' => $this->renderCategory($entityId, $storeId, $context),
            'cms-page' => $this->renderCmsPage($entityId, $context),
            default    => null,
        };
    }

    private function renderProduct(int $id, int $storeId, OutputContextInterface $context): ?string
    {
        try {
            $product = $this->productRepository->getById($id, false, $storeId);
        } catch (\Throwable) {
            return null;
        }

        $title = (string) $product->getName();
        $short = $this->sanitizer->sanitize((string) $product->getShortDescription(), $context);
        $desc  = $this->sanitizer->sanitize((string) $product->getDescription(), $context);
        $product->setCustomerGroupId($context->getCustomerGroupId());
        $price = (float) $product->getFinalPrice();

        $out = "# {$title}\n\n";
        $out .= sprintf("> SKU: %s · Price: %s %s\n\n", $product->getSku(), number_format($price, 2, '.', ''), $context->getCurrencyCode());
        if ($short !== '') {
            $out .= $short . "\n\n";
        }
        if ($desc !== '' && $desc !== $short) {
            $out .= $desc . "\n";
        }
        return $out;
    }

    private function renderCategory(int $id, int $storeId, OutputContextInterface $context): ?string
    {
        try {
            $category = $this->categoryRepository->get($id, $storeId);
        } catch (\Throwable) {
            return null;
        }

        $title = (string) $category->getName();
        $desc  = $this->sanitizer->sanitize((string) $category->getDescription(), $context);

        $out = "# {$title}\n\n";
        if ($desc !== '') {
            $out .= $desc . "\n";
        }
        return $out;
    }

    private function renderCmsPage(int $id, OutputContextInterface $context): ?string
    {
        try {
            $page = $this->pageRepository->getById($id);
        } catch (\Throwable) {
            return null;
        }

        $title   = (string) $page->getTitle();
        $content = $this->sanitizer->sanitize((string) $page->getContent(), $context);

        $out = "# {$title}\n\n";
        if ($content !== '') {
            $out .= $content . "\n";
        }
        return $out;
    }

    private function notFound(): HttpResponse
    {
        $this->response->setHttpResponseCode(404);
        $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->response->setBody("Not found.\n");
        return $this->response;
    }
}
