<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Date_Format;
use InvalidArgumentException;

use function array_diff;
use function array_values;
use function usort;

/**
 * Responsible for determining which backups to retain to:
 * * minimise storage disk usage
 * * ensure backups are retained for specific days/weeks/months/years
 */
class BackupRetention
{
    /* Keep backups for these periods.
     * This does not mean N + N + N + N backups. Rather, it means that for each time period, we should
     * have that many backups available of that frequency.
     * These periods overlap, for example, daily backups for 30 days and weekly backups of 2 would mean the 2
     * weekly backups are masked by the daily backups.
     * These values represent statements such as:
     *  "For N weeks, we are guaranteed to lose no more than 1 week of data"
     */
    private const COUNT_BACKUPS_DAILY   = 7;
    private const COUNT_BACKUPS_WEEKLY  = 4;
    private const COUNT_BACKUPS_MONTHLY = 4;
    private const COUNT_BACKUPS_YEARLY  = 7;


    /** @var Alaress_Date_Format */
    private $now;


    /**
     * @param Alaress_Date_Format|null $now Injectable for deterministic testing
     */
    public function __construct(Alaress_Date_Format $now = null)
    {
        $this->now = $now ?? Alaress_Date_Format::now();
    }


    /**
     * Converts a set of date strings into ADF objects, ordered most-recent to oldest
     *
     * @param string[] $backupDates
     * @return Alaress_Date_Format[]
     * @throws InvalidArgumentException If unable to parse a date
     */
    public function orderDates(array $backupDates): array
    {
        /** @var Alaress_Date_Format[] $dates */
        $dates = [];
        foreach ($backupDates as $backupDate) {
            // For consistency, assume the backup always occurs at the same time of day
            $dates[] = new Alaress_Date_Format("$backupDate {$this->now->getUsingFormat(Alaress_Date_Format::DATABASE_TIME_FORMAT)}", Alaress_Date_Format::DATABASE_FORMAT);
        }

        usort($dates, static function (Alaress_Date_Format $left, Alaress_Date_Format $right): int {
            return $right->compare($left);
        });

        return $dates;
    }


    /**
     * @param array $backups
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDatesToDelete(array $backups): array
    {
        $backupDates = $this->orderDates($backups);

        $datesToDelete          = [];
        $mostRecentRetainedDate = null;
        foreach ($backupDates as $i => $current) {
            $nextOlder = $backupDates[$i + 1] ?? null;

            // Can we remove current without violating any rules?
            if ($this->retainBackup($mostRecentRetainedDate, $current, $nextOlder)) {
                $mostRecentRetainedDate = $current;
            } else {
                $datesToDelete[] = $current->getDatabaseDate();
            }
        }
        return $datesToDelete;
    }


    /**
     * Returns the set of backup dates to retain
     *
     * @param array $backups
     * @return array
     * @throws InvalidArgumentException
     */
    public function getDatesToRetain(array $backups): array
    {
        return array_values(array_diff($backups, $this->getDatesToDelete($backups)));
    }


    /**
     * Returns whether a candidate backup date should be retained by examining the gap
     * that would be generated between the prior and next dates if the candidate date was deleted.
     *
     * @param Alaress_Date_Format|null $mostRecentRetainedDate
     * @param Alaress_Date_Format      $candidateDate
     * @param Alaress_Date_Format|null $nextOlder
     * @return bool
     */
    private function retainBackup(
        ?Alaress_Date_Format $mostRecentRetainedDate,
        Alaress_Date_Format $candidateDate,
        ?Alaress_Date_Format $nextOlder
    ): bool {
        if ($mostRecentRetainedDate === null) {
            return true;
        }
        // Daily
        if ($this->now->diffInDays($candidateDate) <= self::COUNT_BACKUPS_DAILY) {
            return true;
        }
        // Weekly
        if ($this->now->diffInWeeks($candidateDate) <= self::COUNT_BACKUPS_WEEKLY) {
            if (!$nextOlder) {
                return true;
            }
            return $mostRecentRetainedDate->diffInDays($nextOlder) > 7;
        }
        // Monthly
        if ($this->now->diffInMonths($candidateDate) <= self::COUNT_BACKUPS_MONTHLY) {
            if (!$nextOlder) {
                return true;
            }
            return $mostRecentRetainedDate->diffInDays($nextOlder) > (int)(365.25 / 12);
        }
        // Yearly
        if ($this->now->diffInYears($candidateDate) <= self::COUNT_BACKUPS_YEARLY) {
            if (!$nextOlder) {
                return true;
            }
            return $mostRecentRetainedDate->diffInDays($nextOlder) > 365;
        }

        return false;
    }
}
