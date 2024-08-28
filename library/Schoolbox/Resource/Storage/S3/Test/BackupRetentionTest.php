<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3\Test;

use Alaress_Date_Format;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Resource\Storage\S3\BackupRetention;

use function array_map;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Resource\Storage\S3\BackupRetention::class)]
class BackupRetentionTest extends TestCase
{
    /**
     * @param array $input
     * @param array $expected
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrderDates')]
    public function testOrderDates(array $input, array $expected): void
    {
        $br = new BackupRetention();

        $mapFunction = static function (Alaress_Date_Format $date): string {
            return $date->getDatabaseDate();
        };

        $this->assertEquals($expected, array_map($mapFunction, $br->orderDates($input)));
    }


    /**
     * @param BackupRetention $br
     * @param array           $backups
     * @param array           $toRetain
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetDatesToRetain')]
    public function testGetDatesToRetain(BackupRetention $br, array $backups, array $toRetain): void
    {
        $this->assertEquals($toRetain, $br->getDatesToRetain($backups));
    }



    // Data Providers

    /**
     * @return string[][][]
     */
    public static function providerOrderDates(): array
    {
        $unOrdered = [
            '2020-01-01',
            '2018-01-01',
            '2019-01-01',
            '2010-01-01',
            '2015-01-01',
            '2014-01-01',
        ];
        $ordered   = [
            '2020-01-01',
            '2019-01-01',
            '2018-01-01',
            '2015-01-01',
            '2014-01-01',
            '2010-01-01',
        ];

        return [
            [$unOrdered, $ordered],
        ];
    }


    /**
     * @return mixed[][]
     */
    public static function providerGetDatesToRetain(): array
    {
        $now = Alaress_Date_Format::newDatabaseDate('2020-06-01 00:03:00');
        $br  = new BackupRetention($now);

        $currentDate = clone $now;

        $backups = [];
        for ($i = 0; $i < 5000; $i++) {
            $backups[] = $currentDate->getDatabaseDate();
            $currentDate->addDays(-1);
        }

        $toRetain = [
            "2020-06-01",
            "2020-05-31",
            "2020-05-30",
            "2020-05-29",
            "2020-05-28",
            "2020-05-27",
            "2020-05-26",
            "2020-05-25",
            "2020-05-18",
            "2020-05-11",
            "2020-05-04",
            "2020-04-04",
            "2020-03-05",
            "2020-02-04",
            "2020-01-05",
            "2019-01-05",
            "2018-01-05",
            "2017-01-05",
            "2016-01-06",
            "2015-01-06",
            "2014-01-06",
            "2013-01-06",
        ];

        return [
            [$br, $backups, $toRetain],
        ];
    }
}
