<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller;

use Angeo\LlmsTxt\Controller\Index\Index as IndexController;
use Angeo\LlmsTxt\Controller\Index\MdMirror as MdMirrorController;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Frontend router for AI-discovery files.
 *
 * Resolved routes:
 *   /llms.txt        → Index controller, file=llms.txt
 *   /llms-full.txt   → Index controller, file=llms-full.txt
 *   /llms.jsonl      → Index controller, file=llms.jsonl
 *   /llms-full.jsonl → Index controller, file=llms-full.jsonl (alias of .jsonl)
 *   /{path}.md       → MdMirror controller (when md_mirror is enabled)
 *
 * Multi-store: Magento's store-resolution (path-based or code-based) has already
 * applied by the time this router runs; we look at the LAST path segment so the
 * file works whether the request is /llms.txt or /de/llms.txt.
 *
 * @since 3.0.0
 */
class Router implements RouterInterface
{
    public const PARAM_FILE = 'llms_file';
    public const PARAM_MD_PATH = 'md_path';

    private const FILE_ROUTES = [
        'llms.txt'        => true,
        'llms-full.txt'   => true,
        'llms.jsonl'      => true,
        'llms-full.jsonl' => true,
    ];

    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): mixed
    {
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '') {
            return null;
        }

        // Last segment is the filename — handles /llms.txt and /storepath/llms.txt.
        $lastSlash = strrpos($path, '/');
        $tail = $lastSlash === false ? $path : substr($path, $lastSlash + 1);

        if (isset(self::FILE_ROUTES[$tail])) {
            $request
                ->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('index')
                ->setParam(self::PARAM_FILE, $tail)
                ->setDispatched(true);

            return $this->actionFactory->create(IndexController::class);
        }

        // .md mirror route — any path ending in .md
        if (str_ends_with($tail, '.md')) {
            $request
                ->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('md')
                ->setParam(self::PARAM_MD_PATH, $path)
                ->setDispatched(true);

            return $this->actionFactory->create(MdMirrorController::class);
        }

        return null;
    }
}
