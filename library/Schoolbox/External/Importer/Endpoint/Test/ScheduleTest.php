<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Importer\Endpoint\Scheduler;
use Schoolbox\External\Importer\Endpoint\ValidScheduleExecution;

/**
 * Class ScheduleTest
 *
 * @package Schoolbox\External\Importer\Endpoint\Test
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

        $this->assertEquals($expected, $scheduler->isSatisfiedBy($frequency, $now));
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

        $this->assertEquals($expected, $scheduler->isSatisfiedBy($frequency, $now));
    }


    /**
     * Twice Daily: task runs at 4am and 4pm
     *
     * @return array
     */
    public function provider_validTwiceDailyScheduleFrequency(): array
    {
        return [
            'Twice Daily 1' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 03:59:59'),
                false
            ],

            'Twice Daily 2' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 04:00:00'),
                true
            ],

            'Twice Daily 3' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 04:59:59'),
                true
            ],

            'Twice Daily 4' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 05:00:00'),
                false
            ],

            'Twice Daily 5' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 15:59:59'),
                false
            ],

            'Twice Daily 6' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 16:00:00'),
                true
            ],

            'Twice Daily 7' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 16:59:59'),
                true
            ],

            'Twice Daily 8' => [
                Scheduler::TWICE_DAILY,
                strtotime('2019-12-02 17:00:00'),
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

        $this->assertEquals($expected, $scheduler->isSatisfiedBy($frequency, $now));
    }


    /**
     * Daily: task runs at 1am
     *
     * @return array
     */
    public function provider_validDailyScheduleFrequency(): array
    {
        return [
            'Daily 1' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 00:59:59'),
                false
            ],

            'Daily 2' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 01:00:00'),
                true
            ],

            'Daily 3' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 01:59:59'),
                true
            ],

            'Daily 4' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 02:00:00'),
                false
            ],

            'Daily 5' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 12:59:59'),
                false
            ],

            'Daily 6' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 13:00:00'),
                false
            ],

            'Daily 7' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 13:59:59'),
                false
            ],

            'Daily 8' => [
                Scheduler::DAILY,
                strtotime('2019-12-02 14:00:00'),
                false
            ],
        ];
    }
}
