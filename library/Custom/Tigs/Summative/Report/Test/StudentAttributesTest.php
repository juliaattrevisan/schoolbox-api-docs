<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Test;

use Custom\Tigs\Summative\Report\StudentAttributes;
use PHPUnit\Framework\TestCase;
use Schoolbox\User;
use Alaress_Date_Format;
use InvalidArgumentException;

class StudentAttributesTest extends TestCase
{

    /**
     * @param string $yearLevel
     * @param string $date
     * @param int    $expected
     * @dataProvider providerTermDerivation
     * @throws InvalidArgumentException
     */
    public function testTermDerivation(string $yearLevel, string $date, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $now        = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $attributes = new StudentAttributes($user, null, '1', null, $now);

        $this->assertSame($expected, $attributes->getTerm());
    }


    /**
     * @param string $yearLevel
     * @param string $date
     * @param int    $expected
     * @dataProvider providerCurriculumYearLevelNowOverride
     * @throws InvalidArgumentException
     */
    public function testCurriculumYearLevelNowOverride(string $yearLevel, string $date, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $now        = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $attributes = new StudentAttributes($user, null, '1', null, $now);

        $this->assertSame($expected, $attributes->getCurriculumYearLevel());
    }


    /**
     * @param string $yearLevel
     * @param string $termOverride
     * @param int    $expected
     * @throws InvalidArgumentException
     * @dataProvider providerCurriculumYearLevelTermOverride
     */
    public function testCurriculumYearLevelTermOverride(string $yearLevel, string $termOverride, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($user, $termOverride, '1', null, null);

        $this->assertSame($expected, $attributes->getCurriculumYearLevel());
    }


    /**
     * @param string $yearLevel
     * @param string $date
     * @param int    $expected
     * @dataProvider providerCurriculumSemesterNowOverride
     * @throws InvalidArgumentException
     */
    public function testCurriculumSemesterNowOverride(string $yearLevel, string $date, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $now        = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $attributes = new StudentAttributes($user, null, '1', null, $now);

        $this->assertSame($expected, $attributes->getCurriculumSemester());
    }


    /**
     * @param string $yearLevel
     * @param string $termOverride
     * @param int    $expected
     * @throws InvalidArgumentException
     * @dataProvider providerCurriculumSemesterTermOverride
     */
    public function testCurriculumSemesterTermOverride(string $yearLevel, string $termOverride, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($user, $termOverride, '1', null, null);

        $this->assertSame($expected, $attributes->getCurriculumSemester());
    }


    /**
     * @param string $yearLevel
     * @param string $date
     * @param string $expected
     * @throws InvalidArgumentException
     * @dataProvider providerReportStartNowOverride
     */
    public function testReportStartNowOverride(string $yearLevel, string $date, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $now        = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $attributes = new StudentAttributes($user, null, '1', null, $now);

        $this->assertSame($expected, $attributes->getReportStartDate()->getDatabaseDate());
    }


    /**
     * @param string $yearLevel
     * @param string $date
     * @param string $expected
     * @throws InvalidArgumentException
     * @dataProvider providerReportFinishNowOverride
     */
    public function testReportFinishNowOverride(string $yearLevel, string $date, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $now        = new Alaress_Date_Format($date, Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $attributes = new StudentAttributes($user, null, '1', null, $now);

        $this->assertSame($expected, $attributes->getReportFinishDate()->getDatabaseDate());
    }


    // Data Providers


    /**
     * @return array
     */
    public function providerTermDerivation(): array
    {
        return [
            ['10', '2018-01-10', 1],
            ['10', '2018-04-10', 2],
            ['10', '2018-07-10', 3],
            ['10', '2018-10-10', 4],
            ['11', '2018-01-10', 1],
            ['11', '2018-04-10', 2],
            ['11', '2018-07-10', 3],
            ['11', '2018-10-10', 4],
            ['12', '2018-01-10', 1],
            ['12', '2018-04-10', 2],
            ['12', '2018-07-10', 3],
            ['12', '2018-10-10', 4],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumYearLevelNowOverride(): array
    {
        return [
            ['10', '2018-01-10', 10],
            ['10', '2018-04-10', 10],
            ['10', '2018-07-10', 10],
            ['10', '2018-10-10', 10],
            ['11', '2018-01-10', 11],
            ['11', '2018-04-10', 11],
            ['11', '2018-07-10', 11],
            ['11', '2018-10-10', 12],
            ['12', '2018-01-10', 12],
            ['12', '2018-04-10', 12],
            ['12', '2018-07-10', 12],
            ['12', '2018-10-10', 12],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumYearLevelTermOverride(): array
    {
        return [
            ['10', '1', 10],
            ['10', '2', 10],
            ['10', '3', 10],
            ['10', '4', 10],
            ['11', '1', 11],
            ['11', '2', 11],
            ['11', '3', 11],
            ['11', '4', 12],
            ['12', '1', 12],
            ['12', '2', 12],
            ['12', '3', 12],
            ['12', '4', 12],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumSemesterNowOverride(): array
    {
        return [
            ['10', '2018-01-10', 1],
            ['10', '2018-04-10', 1],
            ['10', '2018-07-10', 2],
            ['10', '2018-10-10', 2],
            ['11', '2018-01-10', 1],
            ['11', '2018-04-10', 1],
            ['11', '2018-07-10', 2],
            ['11', '2018-10-10', 1],
            ['12', '2018-01-10', 1],
            ['12', '2018-04-10', 2],
            ['12', '2018-07-10', 2],
            ['12', '2018-10-10', 2],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumSemesterTermOverride(): array
    {
        return [
            ['10', '1', 1],
            ['10', '2', 1],
            ['10', '3', 2],
            ['10', '4', 2],
            ['11', '1', 1],
            ['11', '2', 1],
            ['11', '3', 2],
            ['11', '4', 1],
            ['12', '1', 1],
            ['12', '2', 2],
            ['12', '3', 2],
            ['12', '4', 2],
        ];
    }


    /**
     * @return array
     */
    public function providerReportStartNowOverride(): array
    {
        return [
            ['10', '2018-01-10', '2018-01-01'],  // Term 1
            ['10', '2018-04-10', '2018-01-01'],  // Term 2
            ['10', '2018-07-10', '2018-07-01'],  // Term 3
            ['10', '2018-10-10', '2018-07-01'],  // Term 4
            ['11', '2018-01-10', '2018-01-01'],  // Term 1
            ['11', '2018-04-10', '2018-01-01'],  // Term 2
            ['11', '2018-07-10', '2018-07-01'],  // Term 3
            ['11', '2018-10-10', '2018-10-01'],  // Term 4
            ['12', '2018-01-10', '2017-10-01'],  // Term 1
            ['12', '2018-04-10', '2018-04-01'],  // Term 2
            ['12', '2018-07-10', '2018-04-01'],  // Term 3
            ['12', '2018-10-10', '2018-04-01'],  // Term 4
        ];
    }


    /**
     * @return array
     */
    public function providerReportFinishNowOverride(): array
    {
        return [
            ['10', '2018-01-10', '2018-06-30'],  // Term 1
            ['10', '2018-04-10', '2018-06-30'],  // Term 2
            ['10', '2018-07-10', '2018-12-31'],  // Term 3
            ['10', '2018-10-10', '2018-12-31'],  // Term 4
            ['11', '2018-01-10', '2018-06-30'],  // Term 1
            ['11', '2018-04-10', '2018-06-30'],  // Term 2
            ['11', '2018-07-10', '2018-09-30'],  // Term 3
            ['11', '2018-10-10', '2019-03-31'],  // Term 4
            ['12', '2018-01-10', '2018-03-31'],  // Term 1
            ['12', '2018-04-10', '2018-12-31'],  // Term 2
            ['12', '2018-07-10', '2018-12-31'],  // Term 3
            ['12', '2018-10-10', '2018-12-31'],  // Term 4
        ];
    }

}
