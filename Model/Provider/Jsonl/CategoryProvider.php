<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Jsonl;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Emits one JSONL record per category, with sanitized description and an
 * embedding_text field optimized for vector indexing.
 *
 * @since 3.0.0
 */
class CategoryProvider extends AbstractProvider
{
    private const DESC_MAX = 4000;
    private const EMBED_MAX = 8000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isCategoriesIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store = $context->getStore();
        $storeId = (int) $store->getId();
        $rootCategoryId = (int) $store->getRootCategoryId();

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'description', 'url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('path', ['like' => '1/' . $rootCategoryId . '/%']);
        $collection->setOrder('position', 'ASC');

        foreach ($collection as $category) {
            $name = trim((string) $category->getName());
            if ($name === '') {
                continue;
            }

            $url = $this->urlResolver->resolve(
                UrlResolverInterface::ENTITY_CATEGORY,
                (int) $category->getId(),
                $storeId
            );
            if ($url === null) {
                continue;
            }

            $description = $this->sanitizer->sanitize(
                (string) $category->getDescription(),
                $context,
                self::DESC_MAX
            );

            yield $this->encodeJsonl([
                'entity_type'    => 'category',
                'entity_id'      => (int) $category->getId(),
                'store_code'     => $store->getCode(),
                'store_name'     => (string) $store->getName(),
                'name'           => $name,
                'url'            => $url,
                'description'    => $description,
                'embedding_text' => mb_substr(trim($name . "\n" . $description), 0, self::EMBED_MAX),
            ]);
        }
    }
}
