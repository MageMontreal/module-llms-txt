<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter
 */
class HtmlFilterTest extends TestCase
{
    private HtmlFilter $filter;
    private OutputContextInterface $context;

    protected function setUp(): void
    {
        $this->filter  = new HtmlFilter();
        $this->context = $this->createMock(OutputContextInterface::class);
    }

    public function testStripsTagsAndDecodesEntities(): void
    {
        $html = '<p>Hello <strong>&amp; goodbye</strong></p>';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Hello & goodbye', $out);
    }

    public function testRemovesScriptAndStyleContents(): void
    {
        $html = '<p>Visible.</p><script>alert(1)</script><style>.x{}</style>';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Visible.', $out);
        self::assertStringNotContainsString('alert', $out);
        self::assertStringNotContainsString('.x{}', $out);
    }

    public function testParagraphBreaksConvertToNewlines(): void
    {
        $html = '<p>Para one.</p><p>Para two.</p>';
        $out = $this->filter->filter($html, $this->context);
        self::assertStringContainsString("Para one.\n", $out);
        self::assertStringContainsString('Para two.', $out);
    }

    public function testCommentsRemoved(): void
    {
        $html = 'Text <!-- comment --> more text';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Text more text', $out);
    }

    public function testNonBreakingSpaceNormalized(): void
    {
        $html = "Hello\xc2\xa0world";
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Hello world', $out);
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        self::assertSame('', $this->filter->filter('', $this->context));
    }
}
