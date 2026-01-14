<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Validator\Test;

use Alaress_Date_Format;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DataModel\Model;
use Schoolbox\Learning\Assessment\DataModel\Validator\DateOrder;

#[CoversMethod(DateOrder::class, 'validate')]
class DateOrderValidator extends TestCase
{
    #[DataProvider('getTestValidDateOrderData')]
    public function testValidDateOrder(Model $assessment): void
    {
        DateOrder::validate($assessment);
        /** @phpstan-ignore-next-line - this is required so there's an assertion */
        $this->assertTrue(true);
    }

    /**
     * Data provider for `testValidDateOrder`
     *
     * @return iterable
     */
    public static function getTestValidDateOrderData(): iterable
    {
        yield 'null dates' => [new Model()];
        yield 'null all dates exc. publish' => [new Model()->setPublishDate(self::createDate())];
        yield 'null all dates exc. open' => [new Model()->setOpenDate(self::createDate())];
        yield 'null all dates exc. due' => [new Model()->setDueDate(self::createDate())];
        yield 'null all dates exc. close' => [new Model()->setCloseDate(self::createDate())];

        yield 'publish date < open date' => [
            new Model()
                ->setPublishDate(self::createDate('-1 day'))
                ->setOpenDate(self::createDate())
        ];
        yield 'publish date = open date' => [
            new Model()
                ->setPublishDate(self::createDate())
                ->setOpenDate(self::createDate())
        ];

        yield 'open date < due date' => [
            new Model()
                ->setOpenDate(self::createDate('-1 day'))
                ->setDueDate(self::createDate())
        ];
        yield 'open date = due date' => [
            new Model()
                ->setOpenDate(self::createDate())
                ->setDueDate(self::createDate())
        ];

        yield 'due date < close date' => [
            new Model()
                ->setDueDate(self::createDate('-1 day'))
                ->setCloseDate(self::createDate())
        ];
        yield 'due date = close date' => [
            new Model()
                ->setDueDate(self::createDate())
                ->setCloseDate(self::createDate())
        ];
    }

    #[DataProvider('getTestInvalidDateOrderData')]
    public function testInvalidDateOrder(Model $assessment): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateOrder::validate($assessment);
    }

    /**
     * Data provider for `testInvalidDateOrder`
     *
     * @return iterable
     */
    public static function getTestInvalidDateOrderData(): iterable
    {
        yield 'publish date > open date' => [
            new Model()
                ->setPublishDate(self::createDate('+1 day'))
                ->setOpenDate(self::createDate())
        ];
        yield 'publish date > close date' => [
            new Model()
                ->setPublishDate(self::createDate('+1 day'))
                ->setCloseDate(self::createDate())
        ];
        yield 'open date > due date' => [
            new Model()
                ->setOpenDate(self::createDate('+1 day'))
                ->setDueDate(self::createDate())
        ];
        yield 'due date > close date' => [
            new Model()
                ->setDueDate(self::createDate('+1 day'))
                ->setCloseDate(self::createDate())
        ];
    }

    /**
     * Generate a date for testing.
     *
     * @param string|null $modify If provided, modify the date with this value.
     * @return Alaress_Date_Format
     */
    private static function createDate(?string $modify = null)
    {
        $date = new Alaress_Date_Format('2026-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT);
        if ($modify) {
            $date = $date->modify($modify);
        }
        return $date;
    }
}
