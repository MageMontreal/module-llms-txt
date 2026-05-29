<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Adminhtml\Generate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Admin "Generate Now (Async)" — schedules the next cron tick to run our generation
 * immediately, returning right away so the admin request doesn't block on a
 * minutes-long generation.
 *
 * Implementation: insert a pending row into cron_schedule with scheduled_at = now,
 * which the default cron group picks up within ~60 seconds.
 *
 * @since 3.0.0
 */
class Schedule extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::generate';

    public function __construct(
        Context $context,
        private readonly ScheduleFactory $scheduleFactory,
        private readonly DateTime $dateTime
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $now = $this->dateTime->gmtTimestamp();
            $schedule = $this->scheduleFactory->create();
            $schedule
                ->setJobCode('angeo_llms_generate')
                ->setStatus(\Magento\Cron\Model\Schedule::STATUS_PENDING)
                ->setCreatedAt(date('Y-m-d H:i:s', $now))
                ->setScheduledAt(date('Y-m-d H:i:s', $now))
                ->save();

            $this->messageManager->addSuccessMessage(
                __('Generation queued. The cron will pick it up within ~60 seconds. Check status below to follow progress.')
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Could not schedule generation: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath(
            'adminhtml/system_config/edit/section/angeo_llms'
        );
    }
}
