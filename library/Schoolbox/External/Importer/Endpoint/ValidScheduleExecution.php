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
     * Decide whether to execute the importer
     *
     * @return bool
     */
    public function isSatisfiedBy(string $frequency, int $now): bool
    {
        /* If the frequency is hourly, just do it
         * We'll make the assumption that Support won't configure it
         * to run more frequently than hourly anyway
         */
        if ($frequency == self::HOURLY) {
            return true;
        }

        /* If the frequency is twiceDaily,
         * Check that the current hour is 4am or 4pm
         * If it is, just do it
         * Otherwise, noop
         */
        if (($frequency == self::TWICE_DAILY) && (4 === date('g', $now))) {
            return true;
        }

        /* If the frequency is daily,
         * Check that the current hour is 1am
         * If it is, just do it
         * Otherwise, noop
         */
        if (($frequency == self::DAILY) && (1 == date('G', $now))) {
            return true;
        }

        /* It's not time to run yet
         */
        return false;
    }
}
