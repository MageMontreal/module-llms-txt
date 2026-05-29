<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Source;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source for the Page Builder sanitization strategy dropdown.
 *
 * @since 3.0.0
 */
class PageBuilderStrategy implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::PB_STRATEGY_PRESERVE,
                'label' => __('Preserve everything (only strip wrapper attributes)'),
            ],
            [
                'value' => Config::PB_STRATEGY_EXCLUDE,
                'label' => __('Exclude listed content-types (recommended)'),
            ],
            [
                'value' => Config::PB_STRATEGY_ALLOW,
                'label' => __('Allow only listed content-types (strict)'),
            ],
            [
                'value' => Config::PB_STRATEGY_STRIP,
                'label' => __('Strip ALL Page Builder elements'),
            ],
        ];
    }
}
