<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the "Generate Now" + "Schedule (Async)" buttons inside the system config form.
 *
 * Both buttons submit POST requests with the form_key so Magento's standard CSRF
 * checks apply. The form key is provided by the parent {@see \Magento\Backend\Block\Template}
 * — no extra injection needed; we just expose it via getFormKey() which is already
 * defined on the parent and used here via the .phtml template.
 *
 * @since 3.0.0
 */
class GenerateButton extends Field
{
    /** @var string */
    protected $_template = 'Angeo_LlmsTxt::system/config/generate_button.phtml';

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->toHtml();
    }

    public function getGenerateNowUrl(): string
    {
        return $this->getUrl('angeo_llms/generate/index');
    }

    public function getScheduleUrl(): string
    {
        return $this->getUrl('angeo_llms/generate/schedule');
    }
}