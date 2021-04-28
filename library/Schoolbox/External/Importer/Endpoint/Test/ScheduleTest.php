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
 * @covers \Schoolbox\External\Importer\Endpoint\ValidScheduleExecution
 */
class ScheduleTest extends TestCase
{

    /**
     * @dataProvider provider_validHourlyScheduleFrequency
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
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
    public function provider_validHourlyScheduleFrequency(): array
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
     * @dataProvider provider_validTwiceDailyScheduleFrequency
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
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
    public function provider_validTwiceDailyScheduleFrequency(): array
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
     * @dataProvider provider_validDailyScheduleFrequency
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
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
    public function provider_validDailyScheduleFrequency(): array
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
     * @dataProvider provider_validNoneScheduleFrequency
     *
     * @param string $frequency
     * @param int    $now
     * @param bool   $expected
     */
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
    public function provider_validNoneScheduleFrequency(): array
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
     * @dataProvider provider_schoolboxManagedScheduleFrequency
     *
     * @param string[] $customHours
     * @param int      $now
     * @param bool     $expected
     * @throws PHPUnitFramework\ExpectationFailedException
     * @throws RecursionContext\InvalidArgumentException
     */
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
    public function provider_schoolboxManagedScheduleFrequency(): array
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
