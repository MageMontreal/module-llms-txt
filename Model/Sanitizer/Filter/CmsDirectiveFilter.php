<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;
use Angeo\LlmsTxt\Model\Config;
use Magento\Cms\Model\Template\FilterProvider;
use Psr\Log\LoggerInterface;

/**
 * Resolves Magento CMS directives ({{widget ...}}, {{block ...}}, {{var ...}},
 * {{store ...}}, etc.) by passing the content through Magento's standard
 * {@see FilterProvider::getPageFilter()}.
 *
 * Without this filter, directives appear in llms.txt as literal text — leaking
 * Magento template internals to AI crawlers and breaking embeddings.
 *
 * Runs inside the frontend-emulation scope set up by AbstractGenerator, so
 * widgets resolve to what visitors actually see.
 *
 * Controlled by config: `angeo_llms/sanitizer/resolve_directives` (default = yes).
 *
 * @since 3.0.0
 */
class CmsDirectiveFilter implements SanitizerFilterInterface
{
    public function __construct(
        private readonly FilterProvider $filterProvider,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function filter(string $content, OutputContextInterface $context): string
    {
        if (!$this->config->shouldResolveDirectives($context->getStore())) {
            return $content;
        }

        // Cheap pre-check — avoid spinning up the filter when there's nothing to resolve.
        if (!str_contains($content, '{{')) {
            return $content;
        }

        try {
            $filter = $this->filterProvider->getPageFilter();
            $filter->setStoreId((int) $context->getStore()->getId());
            return (string) $filter->filter($content);
        } catch (\Throwable $e) {
            // CMS filter throws on malformed directives; fall back to raw content rather
            // than abort sanitization entirely.
            $this->logger->info(sprintf(
                '[Angeo LlmsTxt] CmsDirectiveFilter could not resolve directives in store %s: %s',
                $context->getStore()->getCode(),
                $e->getMessage()
            ));
            return $content;
        }
    }
}
