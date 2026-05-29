<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

/**
 * One stage in the {@see SanitizerInterface} pipeline.
 *
 * Filters are pure transformations: input string → output string, given the
 * current output context. They are run sequentially via di.xml ordering.
 *
 * @api
 * @since 3.0.0
 */
interface SanitizerFilterInterface
{
    /**
     * Apply this filter stage.
     */
    public function filter(string $content, OutputContextInterface $context): string;
}
