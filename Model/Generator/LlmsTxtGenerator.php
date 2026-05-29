<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Generates the compact, spec-compliant llms.txt — links + brief descriptions only.
 *
 * Output path: media/angeo/llms/llms_{store_code}.txt
 * URL:         {base_url}/llms.txt
 *
 * @since 3.0.0
 */
class LlmsTxtGenerator extends AbstractGenerator
{
    protected function getFormat(): string
    {
        return OutputContextInterface::FORMAT_LLMS_TXT;
    }

    protected function getVerbosity(): string
    {
        return OutputContextInterface::VERBOSITY_COMPACT;
    }

    protected function getExtension(): string
    {
        return 'txt';
    }

    protected function isFormatEnabled(StoreInterface $store): bool
    {
        return $this->config->isLlmsTxtEnabled($store);
    }
}
