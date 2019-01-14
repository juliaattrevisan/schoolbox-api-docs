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
use InvalidArgumentException;

/**
 * @covers \Custom\Tigs\Summative\Report\StudentAttributes
 * @codeCoverageIgnore
 */
class StudentAttributesTest extends TestCase
{

    /**
     * @dataProvider providerValidYearLevel
     * @param     $validYear
     * @param int $expected
     * @throws InvalidArgumentException
     */
    public function testValidYearLevel($validYear, int $expected): void
    {
        $user       = (new User())->setYear($validYear);
        $attributes = new StudentAttributes($user);

        $this->assertSame($expected, $attributes->getYearLevel());
    }


    /**
     * @dataProvider providerInvalidYearLevel
     * @param $invalidYear
     * @throws InvalidArgumentException
     * @expectedException InvalidArgumentException
     */
    public function testInvalidYearLevel($invalidYear): void
    {
        $user = (new User())->setYear($invalidYear);
        new StudentAttributes($user);
    }


    /**
     * @dataProvider providerIsIb
     * @param string|null $ibFlag
     * @param bool        $expected
     * @throws InvalidArgumentException
     */
    public function testIsIb(?string $ibFlag, bool $expected): void
    {
        $user = (new User())->setYear(10);
        $attributes = new StudentAttributes($user, $ibFlag);

        $this->assertSame($expected, $attributes->isIb());
    }


    /**
     * @dataProvider providerIsMyp
     * @param int    $yearLevel
     * @param string $ibFlag
     * @param bool   $expected
     * @throws InvalidArgumentException
     */
    public function testIsMyp(int $yearLevel, string $ibFlag, bool $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($user, $ibFlag);

        $this->assertSame($expected, $attributes->isMyp());
    }


    /**
     * @dataProvider providerIsDp
     * @param int    $yearLevel
     * @param string $ibFlag
     * @param bool   $expected
     * @throws InvalidArgumentException
     */
    public function testDp(int $yearLevel, string $ibFlag, bool $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($user, $ibFlag);

        $this->assertSame($expected, $attributes->isDp());
    }


    /**
     * @dataProvider providerIsHsc
     * @param int    $yearLevel
     * @param string $ibFlag
     * @param bool   $expected
     * @throws InvalidArgumentException
     */
    public function testHsc(int $yearLevel, string $ibFlag, bool $expected): void
    {
        $user       = (new User())->setYear($yearLevel);
        $attributes = new StudentAttributes($user, $ibFlag);

        $this->assertSame($expected, $attributes->isHsc());
    }


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
        $attributes = new StudentAttributes($user, null, $date);

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
        $attributes = new StudentAttributes($user, null, $date);

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
        $attributes = new StudentAttributes($user, null, $date);

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
        $attributes = new StudentAttributes($user, null, $date);

        $this->assertSame($expected, $attributes->getReportStart()->getDatabaseDate());
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
        $attributes = new StudentAttributes($user, null, $date);

        $this->assertSame($expected, $attributes->getReportFinish()->getDatabaseDate());
    }


    // Data Providers

    /**
     * @return array
     */
    public function providerValidYearLevel(): array
    {
        // yearLevel, yearLevel
        return [
            [1,    1],
            ['1',  1],
            ['01', 1],
            ['1',  1],
            [7,    7],
            [11,  11],
        ];
    }

    /**
     * @return array
     */
    public function providerInvalidYearLevel(): array
    {
        // yearLevel
        return [
            [null],
            [''],
            ['one'],
            ['a non numeric year'],
            ['prep'],
        ];
    }


    /**
     * @return array
     */
    public function providerIsIb(): array
    {
        // ibFlag, isIb
        return [
            [null, false],
            ['0',  false],
            ['1',  true],
        ];

    }

    public function providerIsMyp(): array
    {
        // yearLevel, isIb, isMyp
        return [
            [6,  '1', false],
            [6,  '0', false],
            [7,  '1', true],
            [7,  '0', true],
            [8,  '1', true],
            [8,  '0', true],
            [9,  '1', true],
            [9,  '0', true],
            [10, '1', true],
            [10, '0', true],
            [11, '1', false],
            [11, '0', false],
            [12, '1', false],
            [12, '0', false],
        ];
    }


    public function providerIsDp(): array
    {
        // yearLevel, isIb, isDp
        return [
            [6,  '1', false],
            [6,  '0', false],
            [7,  '1', false],
            [7,  '0', false],
            [8,  '1', false],
            [8,  '0', false],
            [9,  '1', false],
            [9,  '0', false],
            [10, '1', false],
            [10, '0', false],
            [11, '1', true],
            [11, '0', false],
            [12, '1', true],
            [12, '0', false],
        ];
    }

    public function providerIsHsc(): array
    {
        // yearLevel, isIb, isHsc
        return [
            [6,  '1', false],
            [6,  '0', false],
            [7,  '1', false],
            [7,  '0', false],
            [8,  '1', false],
            [8,  '0', false],
            [9,  '1', false],
            [9,  '0', false],
            [10, '1', false],
            [10, '0', false],
            [11, '1', false],
            [11, '0', true],
            [12, '1', false],
            [12, '0', true],
        ];
    }

    /**
     * @return array
     */
    public function providerTermDerivation(): array
    {
        // yearLevel, now, term
        return [
            ['10', '2010-01-10', 1],
            ['10', '2010-04-10', 2],
            ['10', '2010-07-10', 3],
            ['10', '2010-10-10', 4],
            ['11', '2010-01-10', 1],
            ['11', '2010-04-10', 2],
            ['11', '2010-07-10', 3],
            ['11', '2010-10-10', 4],
            ['12', '2010-01-10', 1],
            ['12', '2010-04-10', 2],
            ['12', '2010-07-10', 3],
            ['12', '2010-10-10', 4],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumYearLevelNowOverride(): array
    {
        // yearLevel, yearLevel
        return [
            ['10', '2010-01-10', 10],
            ['10', '2010-04-10', 10],
            ['10', '2010-07-10', 10],
            ['10', '2010-10-10', 10],
            ['11', '2010-01-10', 11],
            ['11', '2010-04-10', 11],
            ['11', '2010-07-10', 11],
            ['11', '2010-10-10', 12],
            ['12', '2010-01-10', 12],
            ['12', '2010-04-10', 12],
            ['12', '2010-07-10', 12],
            ['12', '2010-10-10', 12],
        ];
    }


    /**
     * @return array
     */
    public function providerCurriculumSemesterNowOverride(): array
    {
        // yearLevel, now, curriculumSemester
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
    public function providerReportStartNowOverride(): array
    {
        // yearLevel, now, reportStart
        return [
            ['10', '2010-01-10', '2010-01-01'],  // Term 1
            ['10', '2010-04-10', '2010-01-01'],  // Term 2
            ['10', '2010-07-10', '2010-07-01'],  // Term 3
            ['10', '2010-10-10', '2010-07-01'],  // Term 4
            ['11', '2010-01-10', '2010-01-01'],  // Term 1
            ['11', '2010-04-10', '2010-01-01'],  // Term 2
            ['11', '2010-07-10', '2010-07-01'],  // Term 3
            ['11', '2010-10-10', '2010-10-01'],  // Term 4
            ['12', '2010-01-10', '2009-10-01'],  // Term 1
            ['12', '2010-04-10', '2010-04-01'],  // Term 2
            ['12', '2010-07-10', '2010-04-01'],  // Term 3
            ['12', '2010-10-10', '2010-04-01'],  // Term 4
        ];
    }


    /**
     * @return array
     */
    public function providerReportFinishNowOverride(): array
    {
        // yearLevel, now, reportEnd
        return [
            ['10', '2010-01-10', '2010-06-30'],  // Term 1
            ['10', '2010-04-10', '2010-06-30'],  // Term 2
            ['10', '2010-07-10', '2010-12-31'],  // Term 3
            ['10', '2010-10-10', '2010-12-31'],  // Term 4
            ['11', '2010-01-10', '2010-06-30'],  // Term 1
            ['11', '2010-04-10', '2010-06-30'],  // Term 2
            ['11', '2010-07-10', '2010-09-30'],  // Term 3
            ['11', '2010-10-10', '2011-03-31'],  // Term 4
            ['12', '2010-01-10', '2010-03-31'],  // Term 1
            ['12', '2010-04-10', '2010-12-31'],  // Term 2
            ['12', '2010-07-10', '2010-12-31'],  // Term 3
            ['12', '2010-10-10', '2010-12-31'],  // Term 4
        ];
    }

}
