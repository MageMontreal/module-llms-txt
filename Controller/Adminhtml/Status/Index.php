<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Adminhtml\Status;

use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Returns the per-store generation status as JSON for the admin status widget.
 *
 * @since 3.0.0
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::config';

    public function __construct(
        Context $context,
        private readonly GenerationStatusRepositoryInterface $statusRepository,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $items = [];
        foreach ($this->statusRepository->getAll() as $status) {
            $items[] = [
                'store_code'        => $status->getStoreCode(),
                'format'            => $status->getFormat(),
                'status'            => $status->getStatus(),
                'last_attempt_at'   => $status->getLastAttemptAt(),
                'last_success_at'   => $status->getLastSuccessAt(),
                'last_attempt_iso'  => $status->getLastAttemptAt()
                    ? gmdate('c', $status->getLastAttemptAt()) : null,
                'last_success_iso'  => $status->getLastSuccessAt()
                    ? gmdate('c', $status->getLastSuccessAt()) : null,
                'byte_size'         => $status->getByteSize(),
                'item_count'        => $status->getItemCount(),
                'duration_seconds'  => $status->getDurationSeconds(),
                'error_message'     => $status->getErrorMessage(),
            ];
        }

        return $this->jsonFactory->create()->setData(['items' => $items]);
    }
}
