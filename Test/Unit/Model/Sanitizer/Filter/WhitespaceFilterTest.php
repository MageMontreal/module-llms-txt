<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Sanitizer\Filter\WhitespaceFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Filter\WhitespaceFilter
 */
class WhitespaceFilterTest extends TestCase
{
    private WhitespaceFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new WhitespaceFilter();
    }

    public function testCompactCollapsesToSingleLine(): void
    {
        $context = $this->createMock(OutputContextInterface::class);
        $context->method('getVerbosity')->willReturn(OutputContextInterface::VERBOSITY_COMPACT);

        $input = "line one\n\nline two\nline three";
        self::assertSame('line one line two line three', $this->filter->filter($input, $context));
    }

    public function testFullPreservesParagraphs(): void
    {
        $context = $this->createMock(OutputContextInterface::class);
        $context->method('getVerbosity')->willReturn(OutputContextInterface::VERBOSITY_FULL);

        $input = "line one\n\nline two\n\n\n\nline three";
        // 3+ newlines → exactly 2 newlines
        $out = $this->filter->filter($input, $context);
        self::assertSame("line one\n\nline two\n\nline three", $out);
    }

    public function testEmptyReturnsEmpty(): void
    {
        $context = $this->createMock(OutputContextInterface::class);
        self::assertSame('', $this->filter->filter('', $context));
    }
}
