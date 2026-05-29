<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Sanitizer;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Psr\Log\LoggerInterface;

/**
 * Default {@see SanitizerInterface} implementation — a pipeline of
 * {@see SanitizerFilterInterface} stages applied in order.
 *
 * Filters are injected via di.xml (see etc/di.xml → type=Sanitizer → argument=filters).
 * A failing filter does NOT abort the pipeline; it is logged and the previous
 * stage's output is forwarded as-is. This is deliberate — sanitization is best-effort
 * and a single broken filter should never strand a generation pass.
 *
 * @since 3.0.0
 */
class Sanitizer implements SanitizerInterface
{
    /**
     * @param SanitizerFilterInterface[] $filters  Ordered by di.xml.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $filters = []
    ) {
    }

    public function sanitize(
        string $rawContent,
        OutputContextInterface $context,
        ?int $maxLength = null
    ): string {
        if ($rawContent === '') {
            return '';
        }

        $current = $rawContent;
        foreach ($this->filters as $name => $filter) {
            if (!$filter instanceof SanitizerFilterInterface) {
                continue;
            }
            try {
                $current = $filter->filter($current, $context);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[Angeo LlmsTxt] Sanitizer filter "%s" failed for store %s: %s',
                    is_string($name) ? $name : $filter::class,
                    $context->getStore()->getCode(),
                    $e->getMessage()
                ));
                // continue with previous value
            }
        }

        if ($maxLength !== null && $maxLength > 0 && mb_strlen($current) > $maxLength) {
            $current = $this->truncateOnWordBoundary($current, $maxLength);
        }

        return $current;
    }

    /**
     * Truncate at the last whitespace before $maxLength, appending an ellipsis.
     * Falls back to a hard truncate if no whitespace is found.
     */
    private function truncateOnWordBoundary(string $text, int $maxLength): string
    {
        $hard = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($hard, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.7) {
            $hard = mb_substr($hard, 0, $lastSpace);
        }
        return rtrim($hard) . '…';
    }
}
