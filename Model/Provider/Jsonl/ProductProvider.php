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
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;

/**
 * Emits one JSONL record per product, streamed via entity_id-cursor pagination.
 *
 * Memory stays bounded at one page (default 1000) regardless of catalog size.
 * Each line conforms to etc/jsonl-schema.json.
 *
 * @since 3.0.0
 */
class ProductProvider extends AbstractProvider
{
    private const SHORT_MAX = 2000;
    private const DESC_MAX  = 5000;
    private const EMBED_MAX = 8000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isProductsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store    = $context->getStore();
        $storeId  = (int) $store->getId();
        $pageSize = $this->config->getCollectionPageSize($store);
        $limit    = $this->config->getProductLimit($store);
        $excludeOos = $this->config->isExcludeOutOfStock($store);
        $lastId   = 0;
        $emitted  = 0;

        while (true) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect([
                'sku', 'name', 'price', 'short_description', 'description', 'url_key',
            ]);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ],
            ]);
            $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize($pageSize);
            $collection->setCurPage(1);

            $hasRows = false;
            foreach ($collection as $product) {
                $hasRows = true;
                $lastId  = (int) $product->getId();

                if ($excludeOos && !$this->isInStock($product, $storeId)) {
                    continue;
                }

                $url = $this->urlResolver->resolve(
                    UrlResolverInterface::ENTITY_PRODUCT,
                    (int) $product->getId(),
                    $storeId
                );
                if ($url === null) {
                    continue;
                }

                $name = trim((string) $product->getName());

                $short = $this->sanitizer->sanitize(
                    (string) $product->getShortDescription(),
                    $context,
                    self::SHORT_MAX
                );
                $desc = $this->sanitizer->sanitize(
                    (string) $product->getDescription(),
                    $context,
                    self::DESC_MAX
                );

                $product->setCustomerGroupId($context->getCustomerGroupId());
                $price = (float) $product->getFinalPrice();

                yield $this->encodeJsonl([
                    'entity_type'       => 'product',
                    'entity_id'         => (int) $product->getId(),
                    'store_code'        => $store->getCode(),
                    'store_name'        => (string) $store->getName(),
                    'sku'               => (string) $product->getSku(),
                    'name'              => $name,
                    'url'               => $url,
                    'price'             => $price,
                    'currency'          => $context->getCurrencyCode(),
                    'short_description' => $short,
                    'description'       => $desc,
                    'embedding_text'    => mb_substr(
                        trim($name . "\n" . $short . "\n" . $desc),
                        0,
                        self::EMBED_MAX
                    ),
                ]);

                $emitted++;
                if ($limit > 0 && $emitted >= $limit) {
                    return;
                }
            }

            $collection->clear();
            if (!$hasRows) {
                break;
            }
        }
    }

    private function isInStock(\Magento\Catalog\Model\Product $product, int $storeId): bool
    {
        try {
            $status = $this->stockRegistry->getProductStockStatus(
                (int) $product->getId(),
                $product->getStore() ? (int) $product->getStore()->getWebsiteId() : null
            );
            return (int) $status === 1;
        } catch (\Throwable) {
            return true;
        }
    }
}
