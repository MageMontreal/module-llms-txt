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
 * Final whitespace normalization.
 *
 * Strategy depends on verbosity:
 *  - VERBOSITY_COMPACT (llms.txt links):    collapse to single line.
 *  - VERBOSITY_FULL    (llms-full.txt):     preserve paragraph breaks.
 *  - VERBOSITY_DATASET (JSONL):             preserve newlines for embedding text.
 *
 * @since 3.0.0
 */
class WhitespaceFilter implements SanitizerFilterInterface
{
    public function filter(string $content, OutputContextInterface $context): string
    {
        if ($content === '') {
            return '';
        }

        if ($context->getVerbosity() === OutputContextInterface::VERBOSITY_COMPACT) {
            // Single-line for markdown link descriptions
            $content = preg_replace('/\s+/u', ' ', $content) ?? $content;
            return trim($content);
        }

        // Preserve paragraph structure for full/dataset.
        $content = preg_replace('/[ \t]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
        return trim($content);
    }
}
