<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Llms;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Emits the `## Categories` section for llms.txt / llms-full.txt.
 *
 * - COMPACT: `- [Name](url): short description`
 * - FULL:    each category gets a `### Name` subheading followed by its full description.
 *
 * @since 3.0.0
 */
class CategoryProvider extends AbstractProvider
{
    private const DESC_MAX_COMPACT = 200;
    private const DESC_MAX_FULL    = 4000;

    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
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

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'description', 'url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter(
            'path',
            ['like' => '1/' . $rootCategoryId . '/%']
        );
        $collection->setOrder('position', 'ASC');

        $headerYielded = false;
        $count = 0;

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
                // No URL rewrite — skip rather than emit a broken link.
                continue;
            }

            if (!$headerYielded) {
                yield "## Categories\n\n";
                $headerYielded = true;
            }

            $label = $this->escapeMarkdown($name);
            $rawDesc = (string) $category->getDescription();

            if ($this->isFullTxt($context)) {
                $body = $this->sanitizer->sanitize($rawDesc, $context, self::DESC_MAX_FULL);
                yield "### {$label}\n\n";
                yield "{$url}\n\n";
                if ($body !== '') {
                    yield $body . "\n\n";
                }
            } else {
                $desc = $this->sanitizer->sanitize($rawDesc, $context, self::DESC_MAX_COMPACT);
                $line = "- [{$label}]({$url})";
                if ($desc !== '') {
                    $line .= ': ' . $desc;
                }
                yield $line . "\n";
            }
            $count++;
        }

        if ($headerYielded) {
            yield "\n";
        }

        $context->setShared('category_count', $count);
    }
}
