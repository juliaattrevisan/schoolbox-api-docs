<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Realtime\Mobile;

use Alaress_Date_Format;

class DelayGen
{
    /**
     * Instead of sending all queued notifications immediately after
     * `$this->blackoutEndTime`: jitter the send by this number of seconds to
     * protect against overwhelming notification services.
     */
    public const BLACKOUT_END_JITTER = 60;

    /**
     * If now is within the quiet period, return a delay until the end of the quiet period.
     * else return null (send now).
     *
     * @param string|null $startTime
     * @param string|null $endTime
     * @param string      $currentTime
     * @return array
     */
    public function getQuitePeriod(?string $startTime, ?string $endTime, string $currentTime): array
    {
        // If no start or end time is provided, return null (send now).
        if (null === $startTime || null === $endTime) {
            return [null, null, null];
        }

        if ($startTime <= $endTime) {
            // Start time < end time:
            // the quiet period does not overlap 00:00
            $quiet = ($currentTime >= $startTime && $currentTime <= $endTime);
        } else {
            // Start time > end time:
            // the quiet period overlaps 00:00
            $quiet = ($currentTime >= $startTime || $currentTime <= $endTime);
        }
        if ($quiet) {
            // How many seconds to go until quiet time expires?
            $jitter = random_int(0, self::BLACKOUT_END_JITTER);
            $delayTimestamp = strtotime($endTime) + $jitter;
            $currentTimestamp = strtotime($currentTime);
            $delay = $delayTimestamp - $currentTimestamp;

            /* if delay is negative, it means we need to add a day
             * (and the negative delay will roll back to the actual time)
             */
//            if ($delay < 0) {
//                $delay += 86400; // add a day in seconds
//            }
            return [$delay, $jitter, $delayTimestamp];
        }
        return [null, null, null];
    }
}
