<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Test;

use PHPUnit\Framework as PHPUnitFramework;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Importer\Endpoint\Scheduler;
use Schoolbox\External\Importer\Endpoint\ValidScheduleExecution;
use SebastianBergmann\RecursionContext;

/**
 * Class ScheduleTest
 *
 * @package Schoolbox\External\Importer\Endpoint\Test
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\External\Importer\Endpoint\ValidScheduleExecution::class)]
class ScheduleTest extends TestCase
{

    /**
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidHourlyScheduleFrequency')]
    public function testHourlyScheduleFrequency(string $frequency, int $now, bool $expected): void
    {
        $scheduler = new ValidScheduleExecution();

        self::assertEquals($expected, $scheduler->isSatisfiedBy($frequency, [], $now));
    }


    /**
     * 'hourly' is functionally equivalent to "whenever the system's crontab is set"
     *
     * @return array
     */
    public static function providerValidHourlyScheduleFrequency(): array
    {
        return [
            'Hourly 1' => [
                Scheduler::HOURLY,
                strtotime('2019-12-02 17:13:00'),
                true
            ],

            'Hourly 2' => [
                Scheduler::HOURLY,
                strtotime('2019-12-02 17:13:04'),
                true
            ],

            'Hourly 3' => [
                Scheduler::HOURLY,
                strtotime('2019-12-02 17:12:59'),
                true
            ],
        ];
    }


    /**
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidTwiceDailyScheduleFrequency')]
    public function testTwiceDailyScheduleFrequency(string $frequency, int $now, bool $expected): void
    {
        $scheduler = new ValidScheduleExecution();

        self::assertEquals($expected, $scheduler->isSatisfiedBy($frequency, [], $now));
    }


    /**
     * Twice Daily: task runs at 6am and 6pm
     *
     * @return array
     */
    public static function providerValidTwiceDailyScheduleFrequency(): array
    {
        return [
            'Twice Daily 1' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 05:59:59'),
                false
            ],

            'Twice Daily 2' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 06:00:00'),
                true
            ],

            'Twice Daily 3' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 06:59:59'),
                true
            ],

            'Twice Daily 4' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 07:00:00'),
                false
            ],

            'Twice Daily 5' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 17:59:59'),
                false
            ],

            'Twice Daily 6' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 18:00:00'),
                true
            ],

            'Twice Daily 7' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 18:59:59'),
                true
            ],

            'Twice Daily 8' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 19:00:00'),
                false
            ],
        ];
    }


    /**
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidDailyScheduleFrequency')]
    public function testDailyScheduleFrequency(string $frequency, int $now, bool $expected): void
    {
        $scheduler = new ValidScheduleExecution();

        self::assertEquals($expected, $scheduler->isSatisfiedBy($frequency, [], $now));
    }


    /**
     * Daily: task runs at 9pm
     *
     * @return array
     */
    public static function providerValidDailyScheduleFrequency(): array
    {
        return [
            'Daily 1' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 20:59:59'),
                false
            ],

            'Daily 2' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 21:00:00'),
                true
            ],

            'Daily 3' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 21:59:59'),
                true
            ],

            'Daily 4' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 22:00:00'),
                false
            ],

            'Daily 5' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 08:59:59'),
                false
            ],

            'Daily 6' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 09:00:00'),
                false
            ],

            'Daily 7' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 09:59:59'),
                false
            ],

            'Daily 8' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 10:00:00'),
                false
            ],
        ];
    }


    /**
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidNoneScheduleFrequency')]
    public function testNoneScheduleFrequency(string $frequency, int $now, bool $expected): void
    {
        $scheduler = new ValidScheduleExecution();

        self::assertEquals($expected, $scheduler->isSatisfiedBy($frequency, [], $now));
    }


    /**
     * When no schedule is chosen, do nothing
     *
     * @return array
     */
    public static function providerValidNoneScheduleFrequency(): array
    {
        return [
            'No schedule 1' => [
                '',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
            'No schedule 2' => [
                '      ',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
            'No schedule 3' => [
                ' -- ',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
            'No schedule 4' => [
                'aslfjl;23j4 2l3j4lj;sjaf;jlkSJFU*(',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
            'No schedule 5' => [
                'null',
                strtotime('2019-12-02 17:13:00'),
                false
            ],

            'No schedule 6' => [
                'None',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
            'No schedule 7' => [
                'none',
                strtotime('2019-12-02 17:13:00'),
                false
            ],
        ];
    }


    /**
     *
     * @param string[] $customHours
     * @param int      $now
     * @param bool     $expected
     * @throws PHPUnitFramework\ExpectationFailedException
     * @throws RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSchoolboxManagedScheduleFrequency')]
    public function testSchoolboxManagedScheduleFrequency(array $customHours, int $now, bool $expected): void
    {
        $scheduler = new ValidScheduleExecution();

        self::assertEquals($expected, $scheduler->isSatisfiedBy(Scheduler::SB, $customHours, $now));
    }


    /**
     * Current hour must match one of the configured values
     *
     * @return array[]
     */
    public static function providerSchoolboxManagedScheduleFrequency(): array
    {
        return [
            'No hours'                 => [[], strtotime('2019-12-02 17:13:00'), false],
            'Single matching hour'     => [['00'], strtotime('2019-12-02 00:13:00'), true],
            'Single non-matching hour' => [['00'], strtotime('2019-12-02 01:13:00'), false],
            'Multi matching hour'      => [['00','01'], strtotime('2019-12-02 00:13:00'), true],
            'Multi non-matching hour'  => [['00','01'], strtotime('2019-12-02 02:13:00'), false],
            'Multi matching hour 24'   => [['00','23'], strtotime('2019-12-02 23:13:00'), true],
        ];
    }
}
