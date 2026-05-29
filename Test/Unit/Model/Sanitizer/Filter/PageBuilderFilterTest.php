<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Sanitizer\Filter\PageBuilderFilter;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the Page Builder element filter — the centerpiece feature.
 *
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Filter\PageBuilderFilter
 */
class PageBuilderFilterTest extends TestCase
{
    private Config&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private OutputContextInterface&MockObject $context;
    private StoreInterface&MockObject $store;

    protected function setUp(): void
    {
        $this->config  = $this->createMock(Config::class);
        $this->logger  = $this->createMock(LoggerInterface::class);
        $this->context = $this->createMock(OutputContextInterface::class);
        $this->store   = $this->createMock(StoreInterface::class);
        $this->store->method('getCode')->willReturn('default');
        $this->context->method('getStore')->willReturn($this->store);
    }

    public function testContentWithoutPageBuilderMarkersIsUntouched(): void
    {
        $this->config->expects(self::never())->method('getPageBuilderStrategy');

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html   = '<p>Plain content, no Page Builder.</p>';

        self::assertSame($html, $filter->filter($html, $this->context));
    }

    public function testPreserveStrategyKeepsEverything(): void
    {
        $this->configureStrategy(Config::PB_STRATEGY_PRESERVE);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<div data-content-type="row"><div data-content-type="text"><p>Hello</p></div></div>';
        self::assertSame($html, $filter->filter($html, $this->context));
    }

    public function testStripStrategyRemovesEverythingWithDataContentType(): void
    {
        $this->configureStrategy(Config::PB_STRATEGY_STRIP);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<p>Outside.</p><div data-content-type="row"><span data-content-type="text">Inside</span></div><p>After.</p>';

        $out = $filter->filter($html, $this->context);

        self::assertStringContainsString('Outside.', $out);
        self::assertStringContainsString('After.', $out);
        self::assertStringNotContainsString('data-content-type', $out);
        self::assertStringNotContainsString('Inside', $out);
    }

    public function testExcludeStrategyDropsExcludedTypesAndKeepsOthers(): void
    {
        $this->config->method('getPageBuilderStrategy')->willReturn(Config::PB_STRATEGY_EXCLUDE);
        $this->config->method('getPageBuilderExcludedTypes')->willReturn(['products', 'banner']);
        $this->config->method('getPageBuilderAllowedTypes')->willReturn([]);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<div data-content-type="text"><p>Keep this text.</p></div>'
              . '<div data-content-type="products" data-appearance="carousel">Carousel</div>'
              . '<div data-content-type="banner">Banner content</div>'
              . '<div data-content-type="heading"><h2>Keep heading</h2></div>';

        $out = $filter->filter($html, $this->context);

        self::assertStringContainsString('Keep this text.', $out);
        self::assertStringContainsString('Keep heading', $out);
        self::assertStringNotContainsString('Carousel', $out);
        self::assertStringNotContainsString('Banner content', $out);
    }

    public function testAllowStrategyOnlyKeepsAllowedTypes(): void
    {
        $this->config->method('getPageBuilderStrategy')->willReturn(Config::PB_STRATEGY_ALLOW);
        $this->config->method('getPageBuilderAllowedTypes')->willReturn(['text', 'heading']);
        $this->config->method('getPageBuilderExcludedTypes')->willReturn([]);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<div data-content-type="text">Keep me.</div>'
              . '<div data-content-type="banner">Drop me.</div>'
              . '<div data-content-type="heading">Keep heading.</div>'
              . '<div data-content-type="video">Drop video.</div>';

        $out = $filter->filter($html, $this->context);

        self::assertStringContainsString('Keep me.', $out);
        self::assertStringContainsString('Keep heading.', $out);
        self::assertStringNotContainsString('Drop me.', $out);
        self::assertStringNotContainsString('Drop video.', $out);
    }

    public function testNestedPageBuilderElementsAreRemovedCorrectly(): void
    {
        $this->config->method('getPageBuilderStrategy')->willReturn(Config::PB_STRATEGY_EXCLUDE);
        $this->config->method('getPageBuilderExcludedTypes')->willReturn(['products']);
        $this->config->method('getPageBuilderAllowedTypes')->willReturn([]);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<div data-content-type="row">'
              . '<div data-content-type="column">'
              . '<div data-content-type="text">Survives.</div>'
              . '<div data-content-type="products">Should be dropped including <span>nested</span> content.</div>'
              . '</div>'
              . '</div>';

        $out = $filter->filter($html, $this->context);

        self::assertStringContainsString('Survives.', $out);
        self::assertStringNotContainsString('Should be dropped', $out);
        self::assertStringNotContainsString('nested', $out);
    }

    public function testMalformedHtmlDoesNotAbortPipeline(): void
    {
        $this->configureStrategy(Config::PB_STRATEGY_STRIP);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        $html = '<div data-content-type="row"><p>Unclosed paragraph<div data-content-type="text">Bad nesting';

        // Should not throw — should return a string.
        $out = $filter->filter($html, $this->context);
        self::assertIsString($out);
    }

    public function testEmptyExcludedListFallsBackToDefaults(): void
    {
        $this->config->method('getPageBuilderStrategy')->willReturn(Config::PB_STRATEGY_EXCLUDE);
        $this->config->method('getPageBuilderExcludedTypes')->willReturn([]); // empty → defaults
        $this->config->method('getPageBuilderAllowedTypes')->willReturn([]);

        $filter = new PageBuilderFilter($this->config, $this->logger);
        // 'products' is in the default excluded list
        $html = '<div data-content-type="text">Keep.</div><div data-content-type="products">Drop.</div>';

        $out = $filter->filter($html, $this->context);

        self::assertStringContainsString('Keep.', $out);
        self::assertStringNotContainsString('Drop.', $out);
    }

    public function testEmptyContentIsHandled(): void
    {
        $filter = new PageBuilderFilter($this->config, $this->logger);
        self::assertSame('', $filter->filter('', $this->context));
    }

    private function configureStrategy(string $strategy): void
    {
        $this->config->method('getPageBuilderStrategy')->willReturn($strategy);
        $this->config->method('getPageBuilderExcludedTypes')->willReturn([]);
        $this->config->method('getPageBuilderAllowedTypes')->willReturn([]);
    }
}
