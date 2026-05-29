<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Url;

use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Url\UrlResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Url\UrlResolver
 */
class UrlResolverTest extends TestCase
{
    public function testBatchWarmupAndLookups(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchAll')->willReturn([
            ['entity_id' => 100, 'entity_type' => 'product',  'request_path' => 'p1.html'],
            ['entity_id' => 100, 'entity_type' => 'product',  'request_path' => 'duplicate.html'], // duplicate — first wins
            ['entity_id' => 200, 'entity_type' => 'category', 'request_path' => 'shoes.html'],
            ['entity_id' => 300, 'entity_type' => 'cms-page', 'request_path' => 'about'],
        ]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($store);

        $resolver = new UrlResolver($resource, $repo);

        self::assertSame(
            'https://shop.test/p1.html',
            $resolver->resolve(UrlResolverInterface::ENTITY_PRODUCT, 100, 1)
        );
        self::assertSame(
            'https://shop.test/shoes.html',
            $resolver->resolve(UrlResolverInterface::ENTITY_CATEGORY, 200, 1)
        );
        self::assertSame(
            'https://shop.test/about',
            $resolver->resolve(UrlResolverInterface::ENTITY_CMS_PAGE, 300, 1)
        );

        // Unknown entity → null
        self::assertNull(
            $resolver->resolve(UrlResolverInterface::ENTITY_PRODUCT, 999, 1)
        );
    }

    public function testWarmUpIsIdempotent(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->expects(self::once())->method('fetchAll')->willReturn([]);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($store);

        $resolver = new UrlResolver($resource, $repo);
        $resolver->warmUp(1);
        $resolver->warmUp(1); // second call hits the cache, no second DB call
    }
}
