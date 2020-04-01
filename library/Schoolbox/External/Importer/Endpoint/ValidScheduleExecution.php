<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

/**
 * Class ValidScheduleExecution
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class ValidScheduleExecution
{

    /**
     * Hours when twice-daily and daily occurs successfully
     */
    private const TWICE_DAILY_HOUR = 4;
    private const DAILY_HOUR = 1;


    /**
     * Decide whether to execute the importer
     *
     * @param string $frequency
     * @param int    $now
     * @return bool
     */
    public function isSatisfiedBy(string $frequency, int $now): bool
    {

        /* If the frequency is Schoolbox Managed, just do it
         * The importer should run whenever it's configured to run and we have a custom crontab for this usage
         * Functionally this is identical to Hourly in that it means "whenever schoolbox told it to run"
         *
         * This option is here for ui feedback, basically so if questions are asked of Support they know
         * to look at the setup in crontab
         */
        if ($frequency === Scheduler::SB) {
            return true;
        }

        /* If the frequency is hourly, just do it
         * By default, this is the crontab configuration for sisImporter
         */
        if ($frequency === Scheduler::HOURLY) {
            return true;
        }

        /* If the frequency is twiceDaily,
         * Check that the current hour is 4am or 4pm
         * If it is, just do it
         * Otherwise, noop
         */
        if (($frequency === Scheduler::TWICE_DAILY) && (self::TWICE_DAILY_HOUR === (int)date('g', $now))) {
            return true;
        }

        /* If the frequency is daily,
         * Check that the current hour is 1am
         * If it is, just do it
         * Otherwise, noop
         */
        if (($frequency === Scheduler::DAILY) && (self::DAILY_HOUR === (int)date('G', $now))) {
            return true;
        }

        /* It's not time to run yet
         */
        return false;
    }
}
