<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\Test;

use Alaress_Date_Format;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Custom\Tigs\Summative\StudentAttributes::class)]
class StudentAttributesTest extends TestCase
{
    /**
     * Test we cannot generate a report for a soft-deleted student
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     */
    public function testDeletedUser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = (new User())
            ->setYear(10)
            ->setDeletedAt(new Alaress_Date_Format('2010-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT));
        new StudentAttributes($this->getConfigLoader(), $user);
    }


    /**
     * Tests the system can detect misconfigured term dates
     *
     * @param array $invalidTermDates
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidTermDates')]
    public function testInvalidTermDates(array $invalidTermDates): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = (new User())->setYear(10);
        new StudentAttributes(new ArrayLoader($invalidTermDates), $user);
    }


    /**
     * Test we don't attempt to produce a report for a student too far in the past
     *
     *
     */
    public function testInvalidHistoricalReport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = (new User())->setYear(7);
        new StudentAttributes($this->getConfigLoader(), $user, null, '2010-02-15', '2011-02-15');
    }


    /**
     * Tests that we can detect whether the report is current or historical
     *
     * @param null|string $reportDate
     * @param null|string $now
     * @param bool   $isHistorical
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurrentOrHistoricalReport')]
    public function testCurrentOrHistoricalReport(?string $reportDate, ?string $now, bool $isHistorical): void
    {
        $user = (new User())->setYear(7);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $now);

        $this->assertSame($isHistorical, $attributes->isHistorical());
        $this->assertSame(!$isHistorical, $attributes->isCurrent());
    }


    /**
     * Test that we can derive a student year level for a current or historical report
     *
     * @param int|string $currentYear
     * @param string     $reportDate
     * @param string     $now
     * @param int        $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidYearLevel')]
    public function testValidYearLevel($currentYear, string $reportDate, string $now, int $expected): void
    {
        $user       = (new User())->setYear($currentYear);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $now);

        $this->assertSame($expected, $attributes->getYearLevel());
    }


    /**
     * Test that an exception is thrown if we supply invalid year level input
     *
     * @param string $invalidYear
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidYearLevel')]
    public function testInvalidYearLevel($invalidYear): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = (new User())->setYear($invalidYear);
        new StudentAttributes($this->getConfigLoader(), $user);
    }


    /**
     * Test that we cannot generate a report for the future
     *
     * @param string $reportDate
     * @param string $nowDate
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidFutureReport')]
    public function testInvalidFutureReport(string $reportDate, string $nowDate): void
    {
        $this->expectException(InvalidArgumentException::class);
        $user = (new User())->setYear(10);
        new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $nowDate);
    }


    /**
     * @param string      $yearLevel
     * @param string|null $ibFlag
     * @param string      $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetExplanationOfGradesType')]
    public function testGetExplanationOfGradesType(string $yearLevel, ?string $ibFlag, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, $ibFlag, '2010-01-01', '2010-01-01');

        $this->assertSame($expected, $attributes->getExplanationOfGradesType());
    }


    /**
     * @param string $yearLevel
     * @param string $reportDate
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetReportDateCalendarYear')]
    public function testGetReportDateCalendarYear(string $yearLevel, string $reportDate, int $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getCalendarYear());
    }


    /**
     * Tests the ibFlag input is interpreted as expected
     *
     * @param string|null $ibFlag
     * @param bool        $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsIb')]
    public function testIsIb(?string $ibFlag, bool $expected): void
    {
        $user       = (new User())->setYear(10);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, $ibFlag);

        $this->assertSame($expected, $attributes->isIb());
    }


    /**
     * Tests that we can detect current and historical MYP students
     *
     * @param int    $currentYearLevel
     * @param string $ibFlag
     * @param string $reportDate
     * @param string $nowDate
     * @param bool   $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsMyp')]
    public function testIsMyp(int $currentYearLevel, string $ibFlag, string $reportDate, string $nowDate, bool $expected): void
    {
        $user       = (new User())->setYear($currentYearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, $ibFlag, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->isMyp());
    }


    /**
     * Tests that we can detect current and historical DP students
     *
     * @param int    $currentYearLevel
     * @param string $ibFlag
     * @param string $reportDate
     * @param string $nowDate
     * @param bool   $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsDp')]
    public function testDp(int $currentYearLevel, string $ibFlag, string $reportDate, string $nowDate, bool $expected): void
    {
        $user       = (new User())->setYear($currentYearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, $ibFlag, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->isDp());
    }


    /**
     * Tests that we can detect current and historical HSC students
     *
     * @param int    $currentYearLevel
     * @param string $ibFlag
     * @param string $reportDate
     * @param string $nowDate
     * @param bool   $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsHsc')]
    public function testHsc(int $currentYearLevel, string $ibFlag, string $reportDate, string $nowDate, bool $expected): void
    {
        $user       = (new User())->setYear($currentYearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, $ibFlag, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->isHsc());
    }


    /**
     * Tests that we can derive a term from a date
     *
     * @param string $reportDate
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTermDerivation')]
    public function testTermDerivation(string $reportDate, int $expected): void
    {
        $user       = (new User())->setYear(10);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getTerm());
    }


    /**
     * Tests we can derive a student's curriculum year for both current and historical reports
     *
     * @param string $currentYearLevel
     * @param string $reportDate
     * @param string $nowDate
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurriculumYearLevel')]
    public function testCurriculumYearLevel(string $currentYearLevel, string $reportDate, string $nowDate, int $expected): void
    {
        $user       = (new User())->setYear($currentYearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->getCurriculumYearLevel());
    }


    /**
     * Tests we can derive a student's current and historical curriculum semester
     *
     * @param string $currentYearLevel
     * @param string $reportDate
     * @param string $nowDate
     * @param int    $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurriculumSemester')]
    public function testCurriculumSemester(string $currentYearLevel, string $reportDate, string $nowDate, int $expected): void
    {
        $user       = (new User())->setYear($currentYearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->getCurriculumSemester());
    }


    /**
     * Test we can derive the date the curriculum semester started for a current report
     *
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurrentReportCurriculumSemesterStart')]
    public function testCurrentReportCurriculumSemesterStart(string $yearLevel, string $reportDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getCurriculumSemesterStart()->getDatabase());
    }


    /**
     * Test we can derive the date the curriculum semester started for a historical report
     *
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $nowDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHistoricalReportCurriculumSemesterStart')]
    public function testHistoricalReportCurriculumSemesterStart(string $yearLevel, string $reportDate, string $nowDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->getCurriculumSemesterStart()->getDatabase());
    }


    /**
     * Test we can derive the date the curriculum semester started for a current report
     *
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurrentReportCurriculumSemesterFinish')]
    public function testCurrentReportCurriculumSemesterFinish(string $yearLevel, string $reportDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getCurriculumSemesterFinish()->getDatabase());
    }


    /**
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurrentReportCurriculumYearStart')]
    public function testCurrentReportCurriculumYearStart(string $yearLevel, string $reportDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getCurriculumYearStart()->getDatabaseDate());
    }


    /**
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $nowDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHistoricalReportCurriculumYearStart')]
    public function testHistoricalReportCurriculumYearStart(string $yearLevel, string $reportDate, string $nowDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $nowDate);

        $this->assertSame($expected, $attributes->getCurriculumYearStart()->getDatabaseDate());
    }


    /**
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurrentReportCurriculumYearFinish')]
    public function testCurrentReportCurriculumYearFinish(string $yearLevel, string $reportDate, string $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);

        $this->assertSame($expected, $attributes->getCurriculumYearFinish()->getDatabaseDate());
    }


    /**
     * @param string $yearLevel
     * @param string $reportDate
     * @param string $s1Start
     * @param string $s1Finish
     * @param string $s2Start
     * @param string $s2Finish
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCurriculumSemesters')]
    public function testCurriculumSemesters(
        string $yearLevel,
        string $reportDate,
        string $s1Start,
        string $s1Finish,
        string $s2Start,
        string $s2Finish
    ): void {
        $user                = (new User())->setYear($yearLevel);
        $attributes          = new StudentAttributes($this->getConfigLoader(), $user, null, $reportDate, $reportDate);
        $curriculumSemesters = $attributes->getCurriculumSemesters();

        $this->assertSame(1, $curriculumSemesters[1]->getOrdinal());
        $this->assertSame($s1Start, $curriculumSemesters[1]->getStart()->getDatabase());
        $this->assertSame($s1Finish, $curriculumSemesters[1]->getFinish()->getDatabase());

        $this->assertSame(2, $curriculumSemesters[2]->getOrdinal());
        $this->assertSame($s2Start, $curriculumSemesters[2]->getStart()->getDatabase());
        $this->assertSame($s2Finish, $curriculumSemesters[2]->getFinish()->getDatabase());
    }


    // Data Providers


    /**
     * @return array
     */
    public static function providerInvalidTermDates(): array
    {
        return [
            // term 4 not defined
            [[
                 Config::_TIGS_START_TERM_1 => '31 January',
                 Config::_TIGS_START_TERM_2 => '29 April',
                 Config::_TIGS_START_TERM_3 => '22 July',
             ]],
            // term 1 empty
            [[
                 Config::_TIGS_START_TERM_1 => '',
                 Config::_TIGS_START_TERM_2 => '29 April',
                 Config::_TIGS_START_TERM_3 => '22 July',
                 Config::_TIGS_START_TERM_4 => '14 October',
             ]],
            // term 1 null
            [[
                 Config::_TIGS_START_TERM_1 => null,
                 Config::_TIGS_START_TERM_2 => '29 April',
                 Config::_TIGS_START_TERM_3 => '22 July',
                 Config::_TIGS_START_TERM_4 => '14 October',
             ]],
            // term 1 invalid
            [[
                 Config::_TIGS_START_TERM_1 => '30/30',
                 Config::_TIGS_START_TERM_2 => '29 April',
                 Config::_TIGS_START_TERM_3 => '22 July',
                 Config::_TIGS_START_TERM_4 => '14 October',
             ]],
        ];
    }


    /**
     * @return array
     */
    public static function providerCurrentOrHistoricalReport(): array
    {
        // $reportDate, $now, bool $isHistorical
        return [
            [null, null, false],
            ['2010-10-10', '2010-10-11', true],     // one day ago
            ['2010-10-10', '2010-10-10', false],    // now
        ];
    }


    /**
     * @return array
     */
    public static function providerValidYearLevel(): array
    {
        // currentYearLevel, reportDate, nowDate, reportYearLevel
        return [
            // current report
            [ '7', '2010-10-10', '2010-10-10',   7],
            ['07', '2010-10-10', '2010-10-10',   7],
            [  7,  '2010-10-10', '2010-10-10',   7],
            [ 11,  '2010-10-10', '2010-10-10',  11],
            ['12', '2010-10-10', '2010-10-10',  12],

            // 1 year ago
            [ '8', '2009-10-10', '2010-10-10',   7],
            ['11',  '2009-10-10', '2010-10-10', 10],
            ['12', '2009-10-10', '2010-10-10',  11],

            // 1 year ago, before yearLevel switch
            [' 9', '2009-01-10', '2010-10-10',   7],
            ['11', '2009-01-10', '2010-10-10',   9],
            ['12', '2009-01-10', '2010-10-10',  10],

            // 2 year ago, after yearLevel switch
            [' 9', '2008-10-10', '2010-01-10',   8],
            ['11', '2008-10-10', '2010-01-10',  10],
            ['12', '2008-10-10', '2010-01-10',  11],
        ];
    }

    /**
     * @return array
     */
    public static function providerInvalidYearLevel(): array
    {
        // yearLevel
        return [
            [null],
            [''],
            ['one'],
            ['a non numeric year'],
            ['prep'],
            [0],
            [6],
        ];
    }


    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function providerInvalidFutureReport(): array
    {
        $data = [];
        foreach (['2010-01-15', '2010-02-15', '2010-05-15', '2010-08-15', '2010-11-15'] as $reportDate) {
            $date = new Alaress_Date_Format($reportDate, Alaress_Date_Format::DATABASE_DATE_FORMAT);
            foreach ([1, 3, 6, 9, 12] as $countMonths) {
                $nowDateObject = (clone $date)->addMonths(-$countMonths);
                $data[]        = [$reportDate, $nowDateObject->getDatabase()];
            }
        }
        return $data;
    }


    /**
     * @return array
     */
    public static function providerGetExplanationOfGradesType(): array
    {
        // string $yearLevel, ?bool $isIb, string $expected
        return [
            ['07', null, 'MYP Year 7-10'],
            ['07', '1', 'MYP Year 7-10'],
            ['07', '0', 'MYP Year 7-10'],
            ['08', null, 'MYP Year 7-10'],
            ['08', '1', 'MYP Year 7-10'],
            ['08', '0', 'MYP Year 7-10'],
            ['09', null, 'MYP Year 7-10'],
            ['09', '1', 'MYP Year 7-10'],
            ['09', '0', 'MYP Year 7-10'],
            ['10', null, 'MYP Year 7-10'],
            ['10', '1', 'MYP Year 7-10'],
            ['10', '0', 'MYP Year 7-10'],
            ['11', null, 'Year 11 HSC'],
            ['11', '0', 'Year 11 HSC'],
            ['11', '1', 'Year 11 DP'],
            ['12', null, 'Year 12 HSC'],
            ['12', '0', 'Year 12 HSC'],
            ['12', '1', 'Year 12 DP'],
        ];
    }



    public static function providerGetReportDateCalendarYear(): array
    {
        // string $yearLevel, string $reportDate, int $expected
        return [
            ['07', '2010-01-15', 2010],
            ['07', '2010-02-15', 2010],
            ['07', '2010-05-15', 2010],
            ['07', '2010-08-15', 2010],
            ['07', '2010-11-15', 2010],

            ['08', '2010-01-15', 2010],
            ['08', '2010-02-15', 2010],
            ['08', '2010-05-15', 2010],
            ['08', '2010-08-15', 2010],
            ['08', '2010-11-15', 2010],

            ['09', '2010-01-15', 2010],
            ['09', '2010-02-15', 2010],
            ['09', '2010-05-15', 2010],
            ['09', '2010-08-15', 2010],
            ['09', '2010-11-15', 2010],

            ['10', '2010-01-15', 2010],
            ['10', '2010-02-15', 2010],
            ['10', '2010-05-15', 2010],
            ['10', '2010-08-15', 2010],
            ['10', '2010-11-15', 2010],

            ['11', '2010-01-15', 2010],
            ['11', '2010-02-15', 2010],
            ['11', '2010-05-15', 2010],
            ['11', '2010-08-15', 2010],
            ['11', '2010-11-15', 2010],

            ['12', '2010-01-15', 2010],
            ['12', '2010-02-15', 2010],
            ['12', '2010-05-15', 2010],
            ['12', '2010-08-15', 2010],
            ['12', '2010-11-15', 2010],
        ];
    }


    /**
     * @return array
     */
    public static function providerIsIb(): array
    {
        // ibFlag, isIb
        return [
            [null, false],
            ['0',  false],
            ['1',  true],
        ];
    }


    /**
     * @return array
     */
    public static function providerIsMyp(): array
    {
        // $currentYearLevel, $ibFlag, $reportDate, $nowDate, $expected
        return [
            // Current reports
            [7,  '1', '2010-10-20','2010-10-20', true],
            [7,  '0', '2010-10-20','2010-10-20', true],
            [8,  '1', '2010-10-20','2010-10-20', true],
            [8,  '0', '2010-10-20','2010-10-20', true],
            [9,  '1', '2010-10-20','2010-10-20', true],
            [9,  '0', '2010-10-20','2010-10-20', true],
            [10, '1', '2010-10-20','2010-10-20', true],
            [10, '0', '2010-10-20','2010-10-20', true],
            [11, '1', '2010-10-20','2010-10-20', false],
            [11, '0', '2010-10-20','2010-10-20', false],
            [12, '1', '2010-10-20','2010-10-20', false],
            [12, '0', '2010-10-20','2010-10-20', false],

            // 2 years ago
            [9,  '0', '2008-10-20','2010-10-20', true],
            [10, '0', '2008-10-20','2010-10-20', true],
            [11, '0', '2008-10-20','2010-10-20', true],
            [12, '0', '2008-10-20','2010-10-20', true],

            // 2 years ago, before yearLevel switch
            [10, '0', '2008-01-20','2010-10-20', true],
            [11, '0', '2008-01-20','2010-10-20', true],
            [12, '0', '2008-01-20','2010-10-20', true],

            // 2 years ago, after yearLevel switch
            [9,  '0', '2008-10-20','2010-01-20', true],
            [10, '0', '2008-10-20','2010-01-20', true],
            [11, '0', '2008-10-20','2010-01-20', true],
            [12, '0', '2008-10-20','2010-01-20', false],
        ];
    }


    public static function providerIsDp(): array
    {
        // int $currentYearLevel, string $ibFlag, string $reportDate, string $nowDate, bool $expected
        return [
            // Current report
            [7,  '1', '2010-10-20','2010-10-20', false],
            [7,  '0', '2010-10-20','2010-10-20', false],
            [8,  '1', '2010-10-20','2010-10-20', false],
            [8,  '0', '2010-10-20','2010-10-20', false],
            [9,  '1', '2010-10-20','2010-10-20', false],
            [9,  '0', '2010-10-20','2010-10-20', false],
            [10, '1', '2010-10-20','2010-10-20', false],
            [10, '0', '2010-10-20','2010-10-20', false],
            [11, '1', '2010-10-20','2010-10-20', true],
            [11, '0', '2010-10-20','2010-10-20', false],
            [12, '1', '2010-10-20','2010-10-20', true],
            [12, '0', '2010-10-20','2010-10-20', false],

            // 1 year ago
            [9,  '0', '2009-10-20','2010-10-20', false],
            [9,  '1', '2009-10-20','2010-10-20', false],
            [10, '0', '2009-10-20','2010-10-20', false],
            [10, '1', '2009-10-20','2010-10-20', false],
            [11, '0', '2009-10-20','2010-10-20', false],
            [11, '1', '2009-10-20','2010-10-20', false],
            [12, '0', '2009-10-20','2010-10-20', false],
            [12, '1', '2009-10-20','2010-10-20', true],

            // 1 year ago, before yearLevel switch
            [10, '0', '2009-01-20','2010-10-20', false],
            [10, '1', '2009-01-20','2010-10-20', false],
            [11, '0', '2009-01-20','2010-10-20', false],
            [11, '1', '2009-01-20','2010-10-20', false],
            [12, '0', '2009-01-20','2010-10-20', false],
            [12, '1', '2009-01-20','2010-10-20', false],

            // 1 year ago, after yearLevel switch
            [9,  '0', '2009-10-20','2010-01-20', false],
            [9,  '1', '2009-10-20','2010-01-20', false],
            [10, '0', '2009-10-20','2010-01-20', false],
            [10, '1', '2009-10-20','2010-01-20', false],
            [11, '0', '2009-10-20','2010-01-20', false],
            [11, '1', '2009-10-20','2010-01-20', true],
            [12, '0', '2009-10-20','2010-01-20', false],
            [12, '1', '2009-10-20','2010-01-20', true],
        ];
    }

    public static function providerIsHsc(): array
    {
        // yearLevel, isIb, isHsc
        return [
            // Current report
            [7,  '0', '2010-10-20','2010-10-20', false],
            [7,  '1', '2010-10-20','2010-10-20', false],
            [8,  '0', '2010-10-20','2010-10-20', false],
            [8,  '1', '2010-10-20','2010-10-20', false],
            [9,  '0', '2010-10-20','2010-10-20', false],
            [9,  '1', '2010-10-20','2010-10-20', false],
            [10, '0', '2010-10-20','2010-10-20', false],
            [10, '1', '2010-10-20','2010-10-20', false],
            [11, '0', '2010-10-20','2010-10-20', true],
            [11, '1', '2010-10-20','2010-10-20', false],
            [12, '0', '2010-10-20','2010-10-20', true],
            [12, '1', '2010-10-20','2010-10-20', false],

            // 1 year ago
            [9,  '0', '2009-10-20','2010-10-20', false],
            [9,  '1', '2009-10-20','2010-10-20', false],
            [10, '0', '2009-10-20','2010-10-20', false],
            [10, '1', '2009-10-20','2010-10-20', false],
            [11, '0', '2009-10-20','2010-10-20', false],
            [11, '1', '2009-10-20','2010-10-20', false],
            [12, '0', '2009-10-20','2010-10-20', true],
            [12, '1', '2009-10-20','2010-10-20', false],

            // 1 year ago, before yearLevel switch
            [10, '0', '2009-01-20','2010-10-20', false],
            [10, '1', '2009-01-20','2010-10-20', false],
            [11, '0', '2009-01-20','2010-10-20', false],
            [11, '1', '2009-01-20','2010-10-20', false],
            [12, '0', '2009-01-20','2010-10-20', false],
            [12, '1', '2009-01-20','2010-10-20', false],

            // 1 year ago, after yearLevel switch
            [9,  '0', '2009-10-20','2010-01-20', false],
            [9,  '1', '2009-10-20','2010-01-20', false],
            [10, '0', '2009-10-20','2010-01-20', false],
            [10, '1', '2009-10-20','2010-01-20', false],
            [11, '0', '2009-10-20','2010-01-20', true],
            [11, '1', '2009-10-20','2010-01-20', false],
            [12, '0', '2009-10-20','2010-01-20', true],
            [12, '1', '2009-10-20','2010-01-20', false],
        ];
    }

    /**
     * @return array
     */
    public static function providerTermDerivation(): array
    {
        // now, term
        return [
            ['2010-01-15', 4],
            ['2010-02-15', 1],
            ['2010-05-15', 2],
            ['2010-08-15', 3],
            ['2010-11-15', 4],
        ];
    }


    /**
     * @return array
     */
    public static function providerCurriculumYearLevel(): array
    {
        // $currentYearLevel, $reportDate, $nowDate, $expected
        return [
            // current report
            ['07', '2010-02-15', '2010-02-15',  7], // Term 1
            ['07', '2010-05-15', '2010-05-15',  7], // Term 2
            ['07', '2010-08-15', '2010-08-15',  7], // Term 3
            ['07', '2010-11-15', '2010-11-15',  7], // Term 4

            ['07', '2010-01-15', '2010-01-15',  7], // Term 4
            ['08', '2010-02-15', '2010-02-15',  8], // Term 1
            ['08', '2010-05-15', '2010-05-15',  8], // Term 2
            ['08', '2010-08-15', '2010-08-15',  8], // Term 3
            ['08', '2010-11-15', '2010-11-15',  8], // Term 4

            ['08', '2010-01-15', '2010-01-15',  8], // Term 4
            ['09', '2010-02-15', '2010-02-15',  9], // Term 1
            ['09', '2010-05-15', '2010-05-15',  9], // Term 2
            ['09', '2010-08-15', '2010-08-15',  9], // Term 3
            ['09', '2010-11-15', '2010-11-15',  9], // Term 4

            ['09', '2010-01-15', '2010-01-15',  9], // Term 4
            ['10', '2010-02-15', '2010-02-15', 10], // Term 1
            ['10', '2010-05-15', '2010-05-15', 10], // Term 2
            ['10', '2010-08-15', '2010-08-15', 10], // Term 3
            ['10', '2010-11-15', '2010-11-15', 10], // Term 4

            ['10', '2010-01-15', '2010-01-15', 10], // Term 4
            ['11', '2010-02-15', '2010-02-15', 11], // Term 1
            ['11', '2010-05-15', '2010-05-15', 11], // Term 2
            ['11', '2010-08-15', '2010-08-15', 11], // Term 3
            ['11', '2010-11-15', '2010-11-15', 12], // Term 4

            ['11', '2010-01-15', '2010-01-15', 12], // Term 4
            ['12', '2010-02-15', '2010-02-15', 12], // Term 1
            ['12', '2010-05-15', '2010-05-15', 12], // Term 2
            ['12', '2010-08-15', '2010-08-15', 12], // Term 3
            ['12', '2010-11-15', '2010-11-15', 12], // Term 4

            ['12', '2010-01-15', '2010-01-15', 12], // Term 4

            // report for Term 4', now in Term 1
            ['08', '2010-01-15', '2010-02-15',  7],
            ['09', '2010-01-15', '2010-02-15',  8],
            ['10', '2010-01-15', '2010-02-15',  9],
            ['11', '2010-01-15', '2010-02-15', 10],
            ['12', '2010-01-15', '2010-02-15', 12],

            // report for Term 4', now in Term 2
            ['08', '2010-01-15', '2010-05-15',  7],
            ['09', '2010-01-15', '2010-05-15',  8],
            ['10', '2010-01-15', '2010-05-15',  9],
            ['11', '2010-01-15', '2010-05-15', 10],
            ['12', '2010-01-15', '2010-05-15', 12],

            // report for Term 4', now in Term 3
            ['08', '2010-01-15', '2010-08-15',  7],
            ['09', '2010-01-15', '2010-08-15',  8],
            ['10', '2010-01-15', '2010-08-15',  9],
            ['11', '2010-01-15', '2010-08-15', 10],
            ['12', '2010-01-15', '2010-08-15', 12],

            // report for Term 4', now in Term 4
            ['08', '2010-01-15', '2010-11-15',  7],
            ['09', '2010-01-15', '2010-11-15',  8],
            ['10', '2010-01-15', '2010-11-15',  9],
            ['11', '2010-01-15', '2010-11-15', 10],
            ['12', '2010-01-15', '2010-11-15', 12],

            // report for Term 1, now in Term 2
            ['08', '2010-02-15', '2010-05-15',  8],
            ['09', '2010-02-15', '2010-05-15',  9],
            ['10', '2010-02-15', '2010-05-15', 10],
            ['11', '2010-02-15', '2010-05-15', 11],
            ['12', '2010-02-15', '2010-05-15', 12],

            // report for Term 1, now in Term 3
            ['08', '2010-02-15', '2010-08-15',  8],
            ['09', '2010-02-15', '2010-08-15',  9],
            ['10', '2010-02-15', '2010-08-15', 10],
            ['11', '2010-02-15', '2010-08-15', 11],
            ['12', '2010-02-15', '2010-08-15', 12],

            // report for Term 1, now in Term 4
            ['08', '2010-02-15', '2010-11-15',  8],
            ['09', '2010-02-15', '2010-11-15',  9],
            ['10', '2010-02-15', '2010-11-15', 10],
            ['11', '2010-02-15', '2010-11-15', 11],
            ['12', '2010-02-15', '2010-11-15', 12],

            // report for Term 2, now in Term 3
            ['08', '2010-05-15', '2010-08-15',  8],
            ['09', '2010-05-15', '2010-08-15',  9],
            ['10', '2010-05-15', '2010-08-15', 10],
            ['11', '2010-05-15', '2010-08-15', 11],
            ['12', '2010-05-15', '2010-08-15', 12],

            // report for Term 2, now in Term 4
            ['08', '2010-05-15', '2010-11-15',  8],
            ['09', '2010-05-15', '2010-11-15',  9],
            ['10', '2010-05-15', '2010-11-15', 10],
            ['11', '2010-05-15', '2010-11-15', 11],
            ['12', '2010-05-15', '2010-11-15', 12],

            // report for Term 3, now in Term 4
            ['08', '2010-08-15', '2010-11-15',  8],
            ['09', '2010-08-15', '2010-11-15',  9],
            ['10', '2010-08-15', '2010-11-15', 10],
            ['11', '2010-08-15', '2010-11-15', 11],
            ['12', '2010-08-15', '2010-11-15', 12],
        ];
    }


    /**
     * @return array
     */
    public static function providerCurriculumSemester(): array
    {
        // $currentYearLevel, $reportDate, $nowDate, $expected
        return [
            // current report
            ['07', '2010-02-15', '2010-02-15',  1], // Term 1
            ['07', '2010-05-15', '2010-05-15',  1], // Term 2
            ['07', '2010-08-15', '2010-08-15',  2], // Term 3
            ['07', '2010-11-15', '2010-11-15',  2], // Term 4

            ['07', '2010-01-15', '2010-01-15',  2], // Term 4
            ['08', '2010-02-15', '2010-02-15',  1], // Term 1
            ['08', '2010-05-15', '2010-05-15',  1], // Term 2
            ['08', '2010-08-15', '2010-08-15',  2], // Term 3
            ['08', '2010-11-15', '2010-11-15',  2], // Term 4

            ['08', '2010-01-15', '2010-01-15',  2], // Term 4
            ['09', '2010-02-15', '2010-02-15',  1], // Term 1
            ['09', '2010-05-15', '2010-05-15',  1], // Term 2
            ['09', '2010-08-15', '2010-08-15',  2], // Term 3
            ['09', '2010-11-15', '2010-11-15',  2], // Term 4

            ['09', '2010-01-15', '2010-01-15',  2], // Term 4
            ['10', '2010-02-15', '2010-02-15',  1], // Term 1
            ['10', '2010-05-15', '2010-05-15',  1], // Term 2
            ['10', '2010-08-15', '2010-08-15',  2], // Term 3
            ['10', '2010-11-15', '2010-11-15',  2], // Term 4

            ['10', '2010-01-15', '2010-01-15',  2], // Term 4
            ['11', '2010-02-15', '2010-02-15',  1], // Term 1
            ['11', '2010-05-15', '2010-05-15',  1], // Term 2
            ['11', '2010-08-15', '2010-08-15',  2], // Term 3
            ['11', '2010-11-15', '2010-11-15',  1], // Term 4

            ['11', '2010-01-15', '2010-01-15',  1], // Term 4
            ['12', '2010-02-15', '2010-02-15',  1], // Term 1
            ['12', '2010-05-15', '2010-05-15',  2], // Term 2
            ['12', '2010-08-15', '2010-08-15',  2], // Term 3
            ['12', '2010-11-15', '2010-11-15',  2], // Term 4

            ['12', '2010-01-15', '2010-01-15',  2], // Term 4

            // report for Term 4', now in Term 1
            ['08', '2010-01-15', '2010-02-15',  2],
            ['09', '2010-01-15', '2010-02-15',  2],
            ['10', '2010-01-15', '2010-02-15',  2],
            ['11', '2010-01-15', '2010-02-15',  2],
            ['12', '2010-01-15', '2010-02-15',  1],

            // report for Term 4', now in Term 2
            ['08', '2010-01-15', '2010-05-15',  2],
            ['09', '2010-01-15', '2010-05-15',  2],
            ['10', '2010-01-15', '2010-05-15',  2],
            ['11', '2010-01-15', '2010-05-15',  2],
            ['12', '2010-01-15', '2010-05-15',  1],

            // report for Term 4', now in Term 3
            ['08', '2010-01-15', '2010-08-15',  2],
            ['09', '2010-01-15', '2010-08-15',  2],
            ['10', '2010-01-15', '2010-08-15',  2],
            ['11', '2010-01-15', '2010-08-15',  2],
            ['12', '2010-01-15', '2010-08-15',  1],

            // report for Term 4', now in Term 4
            ['08', '2010-01-15', '2010-11-15',  2],
            ['09', '2010-01-15', '2010-11-15',  2],
            ['10', '2010-01-15', '2010-11-15',  2],
            ['11', '2010-01-15', '2010-11-15',  2],
            ['12', '2010-01-15', '2010-11-15',  1],

            // report for Term 1, now in Term 2
            ['08', '2010-02-15', '2010-05-15',  1],
            ['09', '2010-02-15', '2010-05-15',  1],
            ['10', '2010-02-15', '2010-05-15',  1],
            ['11', '2010-02-15', '2010-05-15',  1],
            ['12', '2010-02-15', '2010-05-15',  1],

            // report for Term 1, now in Term 3
            ['08', '2010-02-15', '2010-08-15',  1],
            ['09', '2010-02-15', '2010-08-15',  1],
            ['10', '2010-02-15', '2010-08-15',  1],
            ['11', '2010-02-15', '2010-08-15',  1],
            ['12', '2010-02-15', '2010-08-15',  1],

            // report for Term 1, now in Term 4
            ['08', '2010-02-15', '2010-11-15',  1],
            ['09', '2010-02-15', '2010-11-15',  1],
            ['10', '2010-02-15', '2010-11-15',  1],
            ['11', '2010-02-15', '2010-11-15',  1],
            ['12', '2010-02-15', '2010-11-15',  1],

            // report for Term 2, now in Term 3
            ['08', '2010-05-15', '2010-08-15',  1],
            ['09', '2010-05-15', '2010-08-15',  1],
            ['10', '2010-05-15', '2010-08-15',  1],
            ['11', '2010-05-15', '2010-08-15',  1],
            ['12', '2010-05-15', '2010-08-15',  2],

            // report for Term 2, now in Term 4
            ['08', '2010-05-15', '2010-11-15',  1],
            ['09', '2010-05-15', '2010-11-15',  1],
            ['10', '2010-05-15', '2010-11-15',  1],
            ['11', '2010-05-15', '2010-11-15',  1],
            ['12', '2010-05-15', '2010-11-15',  2],

            // report for Term 3, now in Term 4
            ['08', '2010-08-15', '2010-11-15',  2],
            ['09', '2010-08-15', '2010-11-15',  2],
            ['10', '2010-08-15', '2010-11-15',  2],
            ['11', '2010-08-15', '2010-11-15',  2],
            ['12', '2010-08-15', '2010-11-15',  2],
        ];
    }


    /**
     * @return array
     */
    public static function providerCurrentReportCurriculumSemesterStart(): array
    {
        // $yearLevel, $reportDate, $expected
        return [
            ['07', '2010-02-15', '2010-01-31 00:00:00'],  // Term 1
            ['07', '2010-05-15', '2010-01-31 00:00:00'],  // Term 2
            ['07', '2010-08-15', '2010-07-22 00:00:00'],  // Term 3
            ['07', '2010-11-15', '2010-07-22 00:00:00'],  // Term 4

            ['07', '2010-01-15', '2009-07-22 00:00:00'],  // Term 4'
            ['08', '2010-02-15', '2010-01-31 00:00:00'],  // Term 1
            ['08', '2010-05-15', '2010-01-31 00:00:00'],  // Term 2
            ['08', '2010-08-15', '2010-07-22 00:00:00'],  // Term 3
            ['08', '2010-11-15', '2010-07-22 00:00:00'],  // Term 4

            ['08', '2010-01-15', '2009-07-22 00:00:00'],  // Term 4'
            ['09', '2010-02-15', '2010-01-31 00:00:00'],  // Term 1
            ['09', '2010-05-15', '2010-01-31 00:00:00'],  // Term 2
            ['09', '2010-08-15', '2010-07-22 00:00:00'],  // Term 3
            ['09', '2010-11-15', '2010-07-22 00:00:00'],  // Term 4

            ['09', '2010-01-15', '2009-07-22 00:00:00'],  // Term 4'
            ['10', '2010-02-15', '2010-01-31 00:00:00'],  // Term 1
            ['10', '2010-05-15', '2010-01-31 00:00:00'],  // Term 2
            ['10', '2010-08-15', '2010-07-22 00:00:00'],  // Term 3
            ['10', '2010-11-15', '2010-07-22 00:00:00'],  // Term 4

            ['10', '2010-01-15', '2009-07-22 00:00:00'],  // Term 4'
            ['11', '2010-02-15', '2010-01-31 00:00:00'],  // Term 1
            ['11', '2010-05-15', '2010-01-31 00:00:00'],  // Term 2
            ['11', '2010-08-15', '2010-07-22 00:00:00'],  // Term 3
            ['11', '2010-11-15', '2010-10-14 00:00:00'],  // Term 4

            ['11', '2010-01-15', '2009-10-14 00:00:00'],  // Term 4'
            ['12', '2010-02-15', '2009-10-14 00:00:00'],  // Term 1
            ['12', '2010-05-15', '2010-04-29 00:00:00'],  // Term 2
            ['12', '2010-08-15', '2010-04-29 00:00:00'],  // Term 3
            ['12', '2010-11-15', '2010-04-29 00:00:00'],  // Term 4

            ['12', '2010-01-15', '2009-04-29 00:00:00'],  // Term 4'
        ];
    }


    /**
     * @return array
     */
    public static function providerHistoricalReportCurriculumSemesterStart(): array
    {
        // $yearLevel, $reportDate, $nowDate, $expected
        return [
            // Display a report from 1 year + 1 term ago
            ['08', '2010-02-15', '2011-05-15', '2010-01-31 00:00:00'],  // Term 1 from 2
            ['08', '2010-05-15', '2011-08-15', '2010-01-31 00:00:00'],  // Term 2 from 3
            ['08', '2010-08-15', '2011-11-15', '2010-07-22 00:00:00'],  // Term 3 from 4
            ['08', '2010-11-15', '2012-01-15', '2010-07-22 00:00:00'],  // Term 4 from 1

            ['09', '2010-01-15', '2011-02-15', '2009-07-22 00:00:00'],  // Term 4' from 1
            ['09', '2010-02-15', '2011-05-15', '2010-01-31 00:00:00'],  // Term 1 from 2
            ['09', '2010-05-15', '2011-08-15', '2010-01-31 00:00:00'],  // Term 2 from 3
            ['09', '2010-08-15', '2011-11-15', '2010-07-22 00:00:00'],  // Term 3 from 4
            ['09', '2010-11-15', '2012-01-15', '2010-07-22 00:00:00'],  // Term 4 from 4'

            ['10', '2010-01-15', '2011-02-15', '2009-07-22 00:00:00'],  // Term 4' from 1
            ['10', '2010-02-15', '2011-05-15', '2010-01-31 00:00:00'],  // Term 1 from 2
            ['10', '2010-05-15', '2011-08-15', '2010-01-31 00:00:00'],  // Term 2 from 3
            ['10', '2010-08-15', '2011-11-15', '2010-07-22 00:00:00'],  // Term 3 from 4
            ['10', '2010-11-15', '2012-01-15', '2010-07-22 00:00:00'],  // Term 4 from 4'

            ['11', '2010-01-15', '2011-02-15', '2009-07-22 00:00:00'],  // Term 4' from 1
            ['11', '2010-02-15', '2011-05-15', '2010-01-31 00:00:00'],  // Term 1 from 2
            ['11', '2010-05-15', '2011-08-15', '2010-01-31 00:00:00'],  // Term 2 from 3
            ['11', '2010-08-15', '2011-11-15', '2010-07-22 00:00:00'],  // Term 3 from 4
            ['11', '2010-11-15', '2012-01-15', '2010-07-22 00:00:00'],  // Term 4 from 4'

            ['12', '2010-01-15', '2011-02-15', '2009-07-22 00:00:00'],  // Term 4' from 1
            ['12', '2010-02-15', '2011-05-15', '2010-01-31 00:00:00'],  // Term 1 from 2
            ['12', '2010-05-15', '2011-08-15', '2010-01-31 00:00:00'],  // Term 2 from 3
            ['12', '2010-08-15', '2011-11-15', '2010-07-22 00:00:00'],  // Term 3 from 4
            ['12', '2010-11-15', '2012-01-15', '2010-10-14 00:00:00'],  // Term 4 from 4'

            ['12', '2010-01-15', '2011-02-15', '2009-07-22 00:00:00'],  // Term 4' from 1
        ];
    }


    /**
     * @return array
     */
    public static function providerCurrentReportCurriculumSemesterFinish(): array
    {
        // yearLevel, now, curriculumSemesterFinish
        return [
            ['07', '2010-02-15', '2010-07-21 23:59:59'],  // Term 1
            ['07', '2010-05-15', '2010-07-21 23:59:59'],  // Term 2
            ['07', '2010-08-15', '2011-01-30 23:59:59'],  // Term 3
            ['07', '2010-11-15', '2011-01-30 23:59:59'],  // Term 4

            ['07', '2010-01-15', '2010-01-30 23:59:59'],  // Term 4'
            ['08', '2010-02-15', '2010-07-21 23:59:59'],  // Term 1
            ['08', '2010-05-15', '2010-07-21 23:59:59'],  // Term 2
            ['08', '2010-08-15', '2011-01-30 23:59:59'],  // Term 3
            ['08', '2010-11-15', '2011-01-30 23:59:59'],  // Term 4

            ['08', '2010-01-15', '2010-01-30 23:59:59'],  // Term 4'
            ['09', '2010-02-15', '2010-07-21 23:59:59'],  // Term 1
            ['09', '2010-05-15', '2010-07-21 23:59:59'],  // Term 2
            ['09', '2010-08-15', '2011-01-30 23:59:59'],  // Term 3
            ['09', '2010-11-15', '2011-01-30 23:59:59'],  // Term 4

            ['09', '2010-01-15', '2010-01-30 23:59:59'],  // Term 4'
            ['10', '2010-02-15', '2010-07-21 23:59:59'],  // Term 1
            ['10', '2010-05-15', '2010-07-21 23:59:59'],  // Term 2
            ['10', '2010-08-15', '2011-01-30 23:59:59'],  // Term 3
            ['10', '2010-11-15', '2011-01-30 23:59:59'],  // Term 4

            ['10', '2010-01-15', '2010-01-30 23:59:59'],  // Term 4'
            ['11', '2010-02-15', '2010-07-21 23:59:59'],  // Term 1
            ['11', '2010-05-15', '2010-07-21 23:59:59'],  // Term 2
            ['11', '2010-08-15', '2010-10-13 23:59:59'],  // Term 3
            ['11', '2010-11-15', '2011-04-28 23:59:59'],  // Term 4

            ['11', '2010-01-15', '2010-04-28 23:59:59'],  // Term 4'
            ['12', '2010-02-15', '2010-04-28 23:59:59'],  // Term 1
            ['12', '2010-05-15', '2011-01-30 23:59:59'],  // Term 2
            ['12', '2010-08-15', '2011-01-30 23:59:59'],  // Term 3
            ['12', '2010-11-15', '2011-01-30 23:59:59'],  // Term 4

            ['12', '2010-01-15', '2010-01-30 23:59:59'],  // Term 4'
        ];
    }


    /**
     * @return array
     */
    public static function providerCurrentReportCurriculumYearStart(): array
    {
        // yearLevel, now, curriculumYearStart
        return [
            ['07', '2010-02-15', '2010-01-31'],  // Term 1
            ['07', '2010-05-15', '2010-01-31'],  // Term 2
            ['07', '2010-08-15', '2010-01-31'],  // Term 3
            ['07', '2010-11-15', '2010-01-31'],  // Term 4

            ['07', '2010-01-15', '2009-01-31'],  // Term 4'
            ['08', '2010-02-15', '2010-01-31'],  // Term 1
            ['08', '2010-05-15', '2010-01-31'],  // Term 2
            ['08', '2010-08-15', '2010-01-31'],  // Term 3
            ['08', '2010-11-15', '2010-01-31'],  // Term 4

            ['08', '2010-01-15', '2009-01-31'],  // Term 4'
            ['09', '2010-02-15', '2010-01-31'],  // Term 1
            ['09', '2010-05-15', '2010-01-31'],  // Term 2
            ['09', '2010-08-15', '2010-01-31'],  // Term 3
            ['09', '2010-11-15', '2010-01-31'],  // Term 4

            ['09', '2010-01-15', '2009-01-31'],  // Term 4'
            ['10', '2010-02-15', '2010-01-31'],  // Term 1
            ['10', '2010-05-15', '2010-01-31'],  // Term 2
            ['10', '2010-08-15', '2010-01-31'],  // Term 3
            ['10', '2010-11-15', '2010-01-31'],  // Term 4

            ['10', '2010-01-15', '2009-01-31'],  // Term 4'
            ['11', '2010-02-15', '2010-01-31'],  // Term 1
            ['11', '2010-05-15', '2010-01-31'],  // Term 2
            ['11', '2010-08-15', '2010-01-31'],  // Term 3
            ['11', '2010-11-15', '2010-10-14'],  // Term 4

            ['11', '2010-01-15', '2009-10-14'],  // Term 4'
            ['12', '2010-02-15', '2009-10-14'],  // Term 1
            ['12', '2010-05-15', '2009-10-14'],  // Term 2
            ['12', '2010-08-15', '2009-10-14'],  // Term 3
            ['12', '2010-11-15', '2009-10-14'],  // Term 4

            ['12', '2010-01-15', '2008-10-14'],  // Term 4'
        ];
    }


    /**
     * @return array
     */
    public static function providerHistoricalReportCurriculumYearStart(): array
    {
        // $yearLevel, $reportDate, $nowDate, $expected
        // 1 year + 1 term ago
        return [
            ['08', '2010-02-15', '2011-05-15', '2010-01-31'],  // Term 1
            ['08', '2010-05-15', '2011-08-15', '2010-01-31'],  // Term 2
            ['08', '2010-08-15', '2011-11-15', '2010-01-31'],  // Term 3
            ['08', '2010-11-15', '2012-01-15', '2010-01-31'],  // Term 4

            ['09', '2010-02-15', '2011-05-15', '2010-01-31'],  // Term 1
            ['09', '2010-05-15', '2011-08-15', '2010-01-31'],  // Term 2
            ['09', '2010-08-15', '2011-11-15', '2010-01-31'],  // Term 3
            ['09', '2010-11-15', '2012-01-15', '2010-01-31'],  // Term 4

            ['09', '2010-01-15', '2011-02-15', '2009-01-31'],  // Term 4'
            ['10', '2010-02-15', '2011-05-15', '2010-01-31'],  // Term 1
            ['10', '2010-05-15', '2011-08-15', '2010-01-31'],  // Term 2
            ['10', '2010-08-15', '2011-11-15', '2010-01-31'],  // Term 3
            ['10', '2010-11-15', '2012-01-15', '2010-01-31'],  // Term 4

            ['10', '2010-01-15', '2011-02-15', '2009-01-31'],  // Term 4'
            ['11', '2010-02-15', '2011-05-15', '2010-01-31'],  // Term 1
            ['11', '2010-05-15', '2011-08-15', '2010-01-31'],  // Term 2
            ['11', '2010-08-15', '2011-11-15', '2010-01-31'],  // Term 3
            ['11', '2010-11-15', '2012-01-15', '2010-01-31'],  // Term 4

            ['11', '2010-01-15', '2011-02-15', '2009-01-31'],  // Term 4'
            ['12', '2010-02-15', '2011-05-15', '2010-01-31'],  // Term 1
            ['12', '2010-05-15', '2011-08-15', '2010-01-31'],  // Term 2
            ['12', '2010-08-15', '2011-11-15', '2010-01-31'],  // Term 3
            ['12', '2010-11-15', '2012-01-15', '2010-10-14'],  // Term 4
        ];
    }


    /**
     * @return array
     */
    public static function providerCurrentReportCurriculumYearFinish(): array
    {
        // yearLevel, now, curriculumYearFinish
        return [
            ['07', '2010-02-15', '2011-01-30'],  // Term 1
            ['07', '2010-05-15', '2011-01-30'],  // Term 2
            ['07', '2010-08-15', '2011-01-30'],  // Term 3
            ['07', '2010-11-15', '2011-01-30'],  // Term 4

            ['07', '2010-01-15', '2010-01-30'],  // Term 4'
            ['08', '2010-02-15', '2011-01-30'],  // Term 1
            ['08', '2010-05-15', '2011-01-30'],  // Term 2
            ['08', '2010-08-15', '2011-01-30'],  // Term 3
            ['08', '2010-11-15', '2011-01-30'],  // Term 4

            ['08', '2010-01-15', '2010-01-30'],  // Term 4'
            ['09', '2010-02-15', '2011-01-30'],  // Term 1
            ['09', '2010-05-15', '2011-01-30'],  // Term 2
            ['09', '2010-08-15', '2011-01-30'],  // Term 3
            ['09', '2010-11-15', '2011-01-30'],  // Term 4

            ['09', '2010-01-15', '2010-01-30'],  // Term 4'
            ['10', '2010-02-15', '2011-01-30'],  // Term 1
            ['10', '2010-05-15', '2011-01-30'],  // Term 2
            ['10', '2010-08-15', '2011-01-30'],  // Term 3
            ['10', '2010-11-15', '2011-01-30'],  // Term 4

            ['10', '2010-01-15', '2010-01-30'],  // Term 4'
            ['11', '2010-02-15', '2010-10-13'],  // Term 1
            ['11', '2010-05-15', '2010-10-13'],  // Term 2
            ['11', '2010-08-15', '2010-10-13'],  // Term 3
            ['11', '2010-11-15', '2012-01-30'],  // Term 4

            ['11', '2010-01-15', '2011-01-30'],  // Term 4'
            ['12', '2010-02-15', '2011-01-30'],  // Term 1
            ['12', '2010-05-15', '2011-01-30'],  // Term 2
            ['12', '2010-08-15', '2011-01-30'],  // Term 3
            ['12', '2010-11-15', '2011-01-30'],  // Term 4

            ['12', '2010-01-15', '2010-01-30'],  // Term 4'
        ];
    }

    /**
     * @return array
     */
    public static function providerCurriculumSemesters(): array
    {
        // $yearLevel, $date, $s1Start, $s1Finish, $s2Start, $s2Finish
        return [
            ['07', '2010-02-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 1
            ['07', '2010-05-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 2
            ['07', '2010-08-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 3
            ['07', '2010-11-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 4

            ['07', '2010-01-15', '2009-01-31 00:00:00', '2009-07-21 23:59:59', '2009-07-22 00:00:00', '2010-01-30 23:59:59'],  // Term 4'
            ['08', '2010-02-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 1
            ['08', '2010-05-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 2
            ['08', '2010-08-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 3
            ['08', '2010-11-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 4

            ['08', '2010-01-15', '2009-01-31 00:00:00', '2009-07-21 23:59:59', '2009-07-22 00:00:00', '2010-01-30 23:59:59'],  // Term 4'
            ['09', '2010-02-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 1
            ['09', '2010-05-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 2
            ['09', '2010-08-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 3
            ['09', '2010-11-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 4

            ['09', '2010-01-15', '2009-01-31 00:00:00', '2009-07-21 23:59:59', '2009-07-22 00:00:00', '2010-01-30 23:59:59'],  // Term 4'
            ['10', '2010-02-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 1
            ['10', '2010-05-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 2
            ['10', '2010-08-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 3
            ['10', '2010-11-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2011-01-30 23:59:59'],  // Term 4

            ['10', '2010-01-15', '2009-01-31 00:00:00', '2009-07-21 23:59:59', '2009-07-22 00:00:00', '2010-01-30 23:59:59'],  // Term 4'
            ['11', '2010-02-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2010-10-13 23:59:59'],  // Term 1
            ['11', '2010-05-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2010-10-13 23:59:59'],  // Term 2
            ['11', '2010-08-15', '2010-01-31 00:00:00', '2010-07-21 23:59:59', '2010-07-22 00:00:00', '2010-10-13 23:59:59'],  // Term 3
            ['11', '2010-11-15', '2010-10-14 00:00:00', '2011-04-28 23:59:59', '2011-04-29 00:00:00', '2012-01-30 23:59:59'],  // Term 4

            ['11', '2010-01-15', '2009-10-14 00:00:00', '2010-04-28 23:59:59', '2010-04-29 00:00:00', '2011-01-30 23:59:59'],  // Term 4'
            ['12', '2010-02-15', '2009-10-14 00:00:00', '2010-04-28 23:59:59', '2010-04-29 00:00:00', '2011-01-30 23:59:59'],  // Term 1
            ['12', '2010-05-15', '2009-10-14 00:00:00', '2010-04-28 23:59:59', '2010-04-29 00:00:00', '2011-01-30 23:59:59'],  // Term 2
            ['12', '2010-08-15', '2009-10-14 00:00:00', '2010-04-28 23:59:59', '2010-04-29 00:00:00', '2011-01-30 23:59:59'],  // Term 3
            ['12', '2010-11-15', '2009-10-14 00:00:00', '2010-04-28 23:59:59', '2010-04-29 00:00:00', '2011-01-30 23:59:59'],  // Term 4

            ['12', '2010-01-15', '2008-10-14 00:00:00', '2009-04-28 23:59:59', '2009-04-29 00:00:00', '2010-01-30 23:59:59'],  // Term 4'
        ];
    }

    // ============== Helpers ==================


    /**
     * Returns a config loader with valid relative dates
     *
     * @return IConfigLoader
     */
    private function getConfigLoader(): IConfigLoader
    {
        $cache = [
            Config::_TIGS_START_TERM_1 => '31 January',
            Config::_TIGS_START_TERM_2 => '29 April',
            Config::_TIGS_START_TERM_3 => '22 July',
            Config::_TIGS_START_TERM_4 => '14 October',
        ];

        return new ArrayLoader($cache);
    }
}
