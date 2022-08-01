<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;

/**
 * From a set of 8 dates (4 tuples) corresponding to a 4-term standard school year,
 * decide which term we are in.
 *
 * If the current datetime isn't between any of those tuples' dates, then we're between
 * terms or we can't tell - revert back to the default semester guess where
 *     jan-jun equates to Semester 1
 *     jul-dec equates to Semester 2
 *
 * Note that tass uses the word "semester" to also mean "term"
 */
class SemesterDetector
{
    /**
     * A set of option related to our ability to detect Current Semester
     */
    private const ACADEMIC_PERIOD_KEYS = [
        'tap1start',
        'tap2start',
        'tap3start',
        'tap4start'
    ];


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * This is the config setting for date_local
     * It's used by the Nat Picker in the optional TASS Academic Periods area
     *
     * @var string
     */
    private string $dateLocalFormat;


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param string          $dateLocalFormat
     */
    public function __construct(LoggerInterface $log, string $dateLocalFormat)
    {
        $this->log = $log;
        $this->dateLocalFormat = $dateLocalFormat;
    }


    /**
     * This bypasses the dependency on tass to fix the way to get Current Semester
     *
     * For the given set of TASS Academic Period dates, find out which "semester" we are currently in
     * A school running 4 term years will have $semester available in tass as 1 through to 4, which
     * isn't what a semester really is, because tass reasons...
     *
     * Check each of the pairs until we find the first match where: Start <= Now <= End
     *
     * @param array $options
     * @return null|int
     * @throws \InvalidArgumentException
     */
    public function detect(array $options): ?int
    {
        $now = \Alaress_Date_Format::now();
        $semester = null;
        $found = false;

        foreach (self::ACADEMIC_PERIOD_KEYS as $index => $startVal) {
            /* If both were defined, check Start <= Now <= End
             * This returns the first semester that fulfils the date requirements
             *
             * Otherwise, continue trying
             */
            $start = trim((string)($options[$startVal] ?? null));

            /* One of the dates is inconclusive - keep going
             */
            if ('' === $start) {
                continue;
            }

            /* Check Alaress Date Format boundaries
             */
            $startDate = (new \Alaress_Date_Format($start, $this->dateLocalFormat))->startOfDay();

            /* If it is, return that $pair's index+1, since:
             *     tap1start is index 0 in ACADEMIC_PERIOD_KEYS
             *     but it corresponds to "semester 1" in tass.
             */
            if ($now->gte($startDate)) {
                $semester = $index + 1;
            }

            /* We found at least one date in the set
             */
            $found = true;
        }

        /* If we found a semester index using the tass Academic Periods,
         * return that date's corresponding index
         */
        if ($semester) {
            $this->log->debug(sprintf(
                'Detected TASS Academic Period %s via DSN options; using that as semester value',
                $semester
            ));

            return $semester;
        }

        /* There was at least one date in the set, but it's in the future
         * Exit early with null, which causes timetable import to skip
         */
        if ($found) {
            $this->log->debug('All user-provided semester dates are in the future - skipping timetable imports.');

            return null;
        }

        /* If none of the Academic Periods help us figure out what semester
         * we're currently in, fall back to the default presumptive behaviour of
         * guessing that Jan-Jun is Semester 1, and everything else is semester 2
         */
        $semester = $this->guessCurrentSemester();

        $this->log->debug(sprintf(
            'Detecting semester %s with classic assumption; using that as semester value',
            $semester
        ));

        return $semester;
    }


    /**
     * Returns 1 if we are currently in first semester, defined by being in January-June inclusive
     * Otherwise semester 2
     *
     * @return int
     */
    private function guessCurrentSemester(): int
    {
        return (int)date('n') <= 6 ? 1 : 2;
    }
}
