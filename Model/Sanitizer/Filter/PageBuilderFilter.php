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
use Psr\Log\LoggerInterface;

/**
 * Page Builder content-element filter.
 *
 * Magento Page Builder (admin/Magento_PageBuilder) emits HTML in which every
 * structural element carries a `data-content-type="row|column|text|heading|
 * image|html|product|products|banner|slider|tabs|video|map|buttons|button-item|
 * block|dynamic-block|html|divider"` attribute.
 *
 * Without this filter, raw Page Builder output ends up in llms.txt with lots
 * of noise: empty <div data-content-type="row"> wrappers, image placeholders,
 * product carousels rendered as "Product Name Product Name Product Name…"
 * because the markup repeats every visible label.
 *
 * Strategies (configurable via `angeo_llms/sanitizer/page_builder_strategy`):
 *
 *  - PRESERVE: keep everything; only strip wrapper attributes (default).
 *  - EXCLUDE:  drop elements whose data-content-type is in the excluded list.
 *              Default excluded list: products, banner, slider, video, map,
 *              buttons, block, dynamic-block, html (these are visual/CTA
 *              elements that pollute the embedding signal).
 *  - ALLOW:    drop everything EXCEPT data-content-types in the allowed list.
 *              Use for strict-mode AEO: allow only text, heading, html-content.
 *  - STRIP:    drop ALL data-content-type-bearing elements (use when CMS pages
 *              are 100% Page Builder and you want to fall through to a custom
 *              "Description" attribute as the source of truth).
 *
 * Implementation notes:
 *  - We use {@see \DOMDocument} (not regex) because Page Builder nests rows
 *    inside columns inside tabs and a regex-based dropper produces wildly
 *    incorrect output on real-world content.
 *  - libxml errors are suppressed and re-emitted as a debug log line; we never
 *    let malformed HTML abort sanitization.
 *  - We DO NOT strip `data-content-type` attributes themselves here — that's
 *    {@see HtmlFilter}'s job further down the pipeline (which calls strip_tags).
 *
 * @since 3.0.0
 */
class PageBuilderFilter implements SanitizerFilterInterface
{
    /**
     * Content-types excluded under the EXCLUDE strategy by default.
     * These are the ones empirically observed to add noise without semantic value
     * for AI retrieval.
     */
    private const DEFAULT_EXCLUDED = [
        'products',        // product carousels (just repeats product names)
        'banner',          // marketing banners (mostly image)
        'slider',          // slide containers
        'slide',           // individual slides
        'video',           // <iframe> embeds
        'map',             // Google Maps embed
        'buttons',         // button containers
        'button-item',     // CTA buttons
        'block',           // CMS block embeds (the block has its own llms.txt entry)
        'dynamic-block',   // dynamic block embeds
        'divider',         // horizontal rules
        'spacer',          // pure layout
    ];

    /**
     * Content-types kept under the ALLOW strategy by default (the "text-ish" ones).
     */
    private const DEFAULT_ALLOWED = [
        'text',
        'heading',
        'html',
        'tabs',
        'tab-item',
        'row',
        'column',
        'column-group',
    ];

    /**
     * Quick sniff: does the content even contain Page Builder markup? If not,
     * skip the expensive DOM parse entirely.
     */
    private const SNIFF_NEEDLE = 'data-content-type=';

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function filter(string $content, OutputContextInterface $context): string
    {
        if ($content === '' || !str_contains($content, self::SNIFF_NEEDLE)) {
            return $content;
        }

        $strategy = $this->config->getPageBuilderStrategy($context->getStore());

        if ($strategy === Config::PB_STRATEGY_PRESERVE) {
            return $content;
        }

        $excluded = $this->config->getPageBuilderExcludedTypes($context->getStore());
        if ($excluded === []) {
            $excluded = self::DEFAULT_EXCLUDED;
        }
        $allowed = $this->config->getPageBuilderAllowedTypes($context->getStore());
        if ($allowed === []) {
            $allowed = self::DEFAULT_ALLOWED;
        }

        return $this->process($content, $strategy, $excluded, $allowed, $context);
    }

    /**
     * Drop the configured Page Builder elements from the markup.
     *
     * @param string[] $excluded
     * @param string[] $allowed
     */
    private function process(
        string $html,
        string $strategy,
        array $excluded,
        array $allowed,
        OutputContextInterface $context
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');

        // libxml chokes on HTML5 and Page Builder's non-standard attributes; we
        // capture and discard the errors and keep going on best-effort.
        $previousLibxmlState = libxml_use_internal_errors(true);

        // Wrap in a synthetic root so we can extract the inner content cleanly,
        // and add a UTF-8 meta so DOMDocument doesn't mis-encode non-ASCII.
        $wrapped = '<?xml encoding="UTF-8"?><!DOCTYPE html><html><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            . '</head><body><div id="angeo-pb-root">'
            . $html
            . '</div></body></html>';

        // LIBXML_HTML_NOIMPLIED + LIBXML_HTML_NODEFDTD would be ideal, but
        // they're unreliable across libxml versions. We use the wrapper approach.
        try {
            $loaded = $dom->loadHTML(
                $wrapped,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
            );
        } catch (\Throwable $e) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlState);
            $this->logger->debug(sprintf(
                '[Angeo LlmsTxt] PageBuilderFilter DOM parse failed in store %s: %s',
                $context->getStore()->getCode(),
                $e->getMessage()
            ));
            return $html;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        // XPath cannot quote arbitrary attribute values portably (single+double
        // mixed quotes), but data-content-type values are always lowercase
        // alphanumeric+dash, so concat-quoting isn't needed.
        $nodes = $xpath->query('//*[@data-content-type]');

        if ($nodes === false || $nodes->length === 0) {
            return $html;
        }

        // Materialize the list — iterating a live NodeList while removing nodes
        // skips siblings.
        $toCheck = [];
        foreach ($nodes as $node) {
            $toCheck[] = $node;
        }

        foreach ($toCheck as $node) {
            if (!$node instanceof \DOMElement || $node->parentNode === null) {
                // Already removed by an ancestor pass.
                continue;
            }
            $type = $node->getAttribute('data-content-type');

            if ($this->shouldRemove($type, $strategy, $excluded, $allowed)) {
                $node->parentNode->removeChild($node);
            }
        }

        // Re-serialize. Extract the inner content of our synthetic root.
        $root = $dom->getElementById('angeo-pb-root');
        if ($root === null) {
            return $html;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child) ?: '';
        }

        return $out;
    }

    /**
     * @param string[] $excluded
     * @param string[] $allowed
     */
    private function shouldRemove(
        string $type,
        string $strategy,
        array $excluded,
        array $allowed
    ): bool {
        return match ($strategy) {
            Config::PB_STRATEGY_STRIP   => true,
            Config::PB_STRATEGY_EXCLUDE => in_array($type, $excluded, true),
            Config::PB_STRATEGY_ALLOW   => !in_array($type, $allowed, true),
            default                     => false,
        };
    }
}
