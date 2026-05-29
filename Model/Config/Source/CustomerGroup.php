<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Source;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source for the "customer group whose pricing to display" config field.
 *
 * @since 3.0.0
 */
class CustomerGroup implements OptionSourceInterface
{
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        try {
            $criteria = $this->searchCriteriaBuilder->create();
            $groups = $this->groupRepository->getList($criteria)->getItems();
            foreach ($groups as $group) {
                $options[] = ['value' => (int) $group->getId(), 'label' => $group->getCode()];
            }
        } catch (\Throwable) {
            // Fallback: at least offer the default "NOT LOGGED IN" group.
            $options[] = ['value' => 0, 'label' => 'NOT LOGGED IN'];
        }
        return $options;
    }
}
