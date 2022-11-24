<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\StickyStatus\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\StickyStatus\Corrector;

/**
 * @covers \Schoolbox\Comms\News\StickyStatus\Corrector
 */
class CorrectorTest extends TestCase
{
    /**
     * @dataProvider getTestFilterData
     * @covers \Schoolbox\Comms\News\StickyStatus\Corrector::apply
     * @param Corrector $corrector
     * @param Model     $article
     * @param bool|null $expectedStickyStatus
     * @return void
     */
    public function testFilter(
        Corrector $corrector,
        Model $article,
        ?bool $expectedStickyStatus,
    ): void {
        $this->assertSame($expectedStickyStatus, $corrector->apply($article)->getSticky());
    }

    /**
     * Data provider for `testFilter`
     *
     * @return iterable
     */
    public function getTestFilterData(): iterable
    {
        yield 'config enabled, article enabled => enabled' => [
            new Corrector(true),
            (new Model())->setSticky(true),
            true,
        ];
        yield 'config disabled, article enabled => disabled' => [
            new Corrector(false),
            (new Model())->setSticky(true),
            false,
        ];
        yield 'config enabled, article disabled => disabled' => [
            new Corrector(true),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config disabled, article disabled => disabled' => [
            new Corrector(false),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config enabled, article unset => null' => [
            new Corrector(true),
            new Model(),
            null,
        ];
        yield 'config disabled, article unset => disabled' => [
            new Corrector(false),
            new Model(),
            false,
        ];
    }
}
