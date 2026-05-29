<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Generator;

use Angeo\LlmsTxt\Model\Generator\GenerationSummary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Generator\GenerationSummary
 */
class GenerationSummaryTest extends TestCase
{
    public function testTracksAllOutcomes(): void
    {
        $s = new GenerationSummary();
        $s->success('default', 1024, 10, 1.5);
        $s->success('de',      2048, 20, 2.0);
        $s->failure('fr',      'database down');
        $s->skip('it');

        self::assertCount(2, $s->getSuccesses());
        self::assertCount(1, $s->getFailures());
        self::assertCount(1, $s->getSkipped());
        self::assertTrue($s->hasFailures());
        self::assertSame(3072, $s->getTotalBytes());
        self::assertSame(30, $s->getTotalItems());
    }

    public function testNoFailuresFlag(): void
    {
        $s = new GenerationSummary();
        $s->success('default', 100, 1, 0.1);
        self::assertFalse($s->hasFailures());
    }
}
