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
        ['tap1start', 'tap1end'],
        ['tap2start', 'tap2end'],
        ['tap3start', 'tap3end'],
        ['tap4start', 'tap4end']
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
     * @return int
     * @throws \InvalidArgumentException
     */
    public function detect(array $options): int
    {
        $now = \Alaress_Date_Format::now();

        foreach (self::ACADEMIC_PERIOD_KEYS as $index => $pair) {
            /* If both were defined, check Start <= Now <= End
             * This returns the first semester that fulfils the date requirements
             *
             * Otherwise, continue trying
             */
            [$startKey, $endKey] = $pair;

            $start = trim((string)($options[$startKey] ?? null));
            $end = trim((string)($options[$endKey] ?? null));

            /* One of the pairs is inconclusive - keep going
             */
            if ('' === $start || '' === $end) {
                continue;
            }

            /* Check Alaress Date Format boundaries
             */
            $startDate = (new \Alaress_Date_Format($start, $this->dateLocalFormat))->startOfDay();
            $endDate = (new \Alaress_Date_Format($end, $this->dateLocalFormat))->endOfDay();

            /* If it is, return that $pair's index+1, since:
             *     (tap1start, tap1end) is index 0 in ACADEMIC_PERIOD_KEYS
             *     but it corresponds to "semester 1" in tass.
             */
            if ($now->between($startDate, $endDate)) {
                $semester = $index + 1;

                $this->log->debug(sprintf(
                    'Detected TASS Academic Period %s via DSN options; using that as semester value',
                    $semester
                ));

                return $semester;
            }
        }

        /* If none of the Academic Periods help us figure out what semester
         * we're currently in, fall back to the default presumptive behaviour of
         * guessing that Jan-Jun is Semester 1, and everything else is semester 2
         */
        $semester = $this->getCurrentSemester();

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
    private function getCurrentSemester(): int
    {
        return (int)date('n') <= 6 ? 1 : 2;
    }
}
