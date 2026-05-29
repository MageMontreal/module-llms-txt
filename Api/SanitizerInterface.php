<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

/**
 * Sanitizer SPI — turns raw Magento content (HTML, Page Builder markup, CMS widget
 * directives, etc.) into plain text or clean markdown suitable for inclusion in
 * llms.txt and JSONL output.
 *
 * The sanitizer pipeline is composed of {@see SanitizerFilterInterface} filters
 * applied in order. The default pipeline (declared in di.xml):
 *
 *   1. {@see Angeo\LlmsTxt\Model\Sanitizer\Filter\CmsDirectiveFilter}
 *        — resolves {{widget ...}}, {{block ...}}, {{var ...}} via Magento's
 *          FilterProvider so the rendered output reflects what visitors see.
 *
 *   2. {@see Angeo\LlmsTxt\Model\Sanitizer\Filter\PageBuilderFilter}
 *        — opt-in/opt-out of Page Builder content elements based on data-content-type.
 *          Configurable via admin → exclude products, banners, sliders, html blocks…
 *
 *   3. {@see Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter}
 *        — strips remaining HTML, normalizes whitespace, trims, decodes entities.
 *
 *   4. {@see Angeo\LlmsTxt\Model\Sanitizer\Filter\LengthFilter}
 *        — truncates to the requested max length on a word boundary.
 *
 * Custom filters can be inserted via di.xml between any of these.
 *
 * @api
 * @since 3.0.0
 */
interface SanitizerInterface
{
    /**
     * Sanitize raw Magento content (HTML / Page Builder / CMS) into plain text.
     *
     * @param string                 $rawContent  Raw input — typically a CMS page, product
     *                                            description, or category description.
     * @param OutputContextInterface $context     Carries the store scope so filters can
     *                                            resolve widgets / variables / store URLs.
     * @param int|null               $maxLength   Optional hard cap (truncates on word boundary,
     *                                            appends ellipsis). Null = no truncation.
     * @return string                Plain UTF-8 text, ready to embed in markdown/JSON.
     */
    public function sanitize(
        string $rawContent,
        OutputContextInterface $context,
        ?int $maxLength = null
    ): string;
}
