<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Alaress_Date_Format;

/**
 * Class ValidActiveEnrolment
 *
 * Decide if the current datetime is between a subject's FROM and TO dates
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar
 */
class ValidActiveEnrolment
{
    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $now;


    /**
     * ValidActiveEnrolment constructor.
     *
     * @param Alaress_Date_Format|null $now
     */
    public function __construct(?Alaress_Date_Format $now = null)
    {
        /* Allow override for $now if you want
         */
        if (null === $now) {
            $this->now = Alaress_Date_Format::now();
        } else {
            $this->now = $now;
        }
    }

    /**
     * If the FROM_DATE is not null and the TO_DATE is not null,
     * check that the current time is between these two dates
     * If we're outside these two dates, skip this subject
     */
    public function isSatisfiedBy(string $originalFrom, string $originalTo): bool
    {
        $from = trim($originalFrom);
        $to = trim($originalTo);

        /* Dates not specified; assume active
         */
        if ('' === $from && '' === $to) {
            return true;
        }

        /* What happens when one date is specified but not the other?
         * Not sure if that's even possible, but we shall see
         *
         * If one, but not both, were provided, assume an active enrolment
         * since the situation is somewhat inconclusive
         */
        if (\count(array_filter([$from, $to])) !== 2) {
            return true;
        }

        /* Dates specified; check that we are between.
         * If we are between, then this is active
         * Otherwise it's not active
         *
         * Note that tass provides date with microseconds
         * e.g. 2021-01-27 00:00:00.0
         */
        $fromDate = Alaress_Date_Format::newDatabaseDate(substr($from, 0, 19));
        $toDate = Alaress_Date_Format::newDatabaseDate(substr($to, 0, 19));

        return ($fromDate->lte($this->now) && $toDate->gte($this->now));
    }
}
