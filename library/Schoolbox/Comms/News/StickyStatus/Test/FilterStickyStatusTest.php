<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\StickyStatus\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\StickyStatus\FilterStickyStatus;

/**
 * @covers \Schoolbox\Comms\News\StickyStatus\FilterStickyStatus
 */
class FilterStickyStatusTest extends TestCase
{
    /**
     * @dataProvider getTestFilterData
     * @covers \Schoolbox\Comms\News\StickyStatus\FilterStickyStatus::filter
     * @param FilterStickyStatus $filterStickyStatus
     * @param Model              $article
     * @param bool|null          $expectedStickyStatus
     * @return void
     */
    public function testFilter(
        FilterStickyStatus $filterStickyStatus,
        Model $article,
        ?bool $expectedStickyStatus,
    ): void {
        $this->assertSame($expectedStickyStatus, $filterStickyStatus->filter($article)->getSticky());
    }

    /**
     * Data provider for `testFilter`
     *
     * @return iterable
     */
    public function getTestFilterData(): iterable
    {
        yield 'config enabled, article enabled => enabled' => [
            new FilterStickyStatus(true),
            (new Model())->setSticky(true),
            true,
        ];
        yield 'config disabled, article enabled => disabled' => [
            new FilterStickyStatus(false),
            (new Model())->setSticky(true),
            false,
        ];
        yield 'config enabled, article disabled => disabled' => [
            new FilterStickyStatus(true),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config disabled, article disabled => disabled' => [
            new FilterStickyStatus(false),
            (new Model())->setSticky(false),
            false,
        ];
        yield 'config enabled, article unset => null' => [
            new FilterStickyStatus(true),
            new Model(),
            null,
        ];
        yield 'config disabled, article unset => disabled' => [
            new FilterStickyStatus(false),
            new Model(),
            false,
        ];
    }
}
