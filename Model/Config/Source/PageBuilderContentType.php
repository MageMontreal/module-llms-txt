<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Canonical list of Page Builder content-types — used as a reference in the admin
 * form when filling the comma-separated "excluded types" / "allowed types" fields.
 *
 * Not used as a multiselect directly (Magento stock multiselects don't round-trip
 * unknown values gracefully), but exposed via a help block so merchants know what
 * names to type.
 *
 * @since 3.0.0
 */
class PageBuilderContentType implements OptionSourceInterface
{
    public const KNOWN_TYPES = [
        'row', 'column-group', 'column', 'tabs', 'tab-item',
        'text', 'heading', 'html',
        'image', 'video', 'map', 'divider', 'spacer',
        'buttons', 'button-item',
        'banner', 'slider', 'slide',
        'products', 'block', 'dynamic-block',
    ];

    public function toOptionArray(): array
    {
        return array_map(
            static fn(string $t) => ['value' => $t, 'label' => $t],
            self::KNOWN_TYPES
        );
    }
}
