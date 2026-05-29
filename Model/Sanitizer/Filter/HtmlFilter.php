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

/**
 * Strips HTML, normalizes whitespace, decodes entities, trims.
 *
 * Runs AFTER {@see CmsDirectiveFilter} and {@see PageBuilderFilter} so the
 * content it sees has already had its widgets resolved and its Page Builder
 * noise removed.
 *
 * @since 3.0.0
 */
class HtmlFilter implements SanitizerFilterInterface
{
    public function filter(string $content, OutputContextInterface $context): string
    {
        if ($content === '') {
            return '';
        }

        // <br>, </p>, </div> → newline before strip_tags so paragraph structure survives.
        $content = preg_replace(
            '#</?(?:br|p|div|li|h[1-6]|tr|blockquote)\s*/?\s*>#i',
            "\n",
            $content
        ) ?? $content;

        // <script> and <style> blocks — keep contents OUT of the output.
        $content = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $content) ?? $content;

        // <!-- comments --> — Page Builder leaves a lot of these.
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;

        $content = strip_tags($content);

        // Decode HTML entities (&amp; &nbsp; &#39; etc.)
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse runs of whitespace, but preserve single newlines as paragraph hints.
        $content = preg_replace('/[ \t\x{00A0}]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        // Trim each line; drop empty lines at start/end.
        $lines = array_map('trim', explode("\n", $content));
        return trim(implode("\n", $lines));
    }
}
