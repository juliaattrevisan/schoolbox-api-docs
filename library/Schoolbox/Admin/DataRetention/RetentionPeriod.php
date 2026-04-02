<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

class RetentionPeriod
{
    private ?\Alaress_Date_Format $date = null; // database format e.g. '2024-01-01 00:00:00'

    /**
     * Construct with an optional day-count. Use null to indicate "retain indefinitely".
     * Accepts integer, numeric string, or null.
     * @param int|null $days
     * @throws SchoolboxException
     */
    public function __construct(private int|null $days = null)
    {
        if (null !== $days) {
            // Compute database date using the project's date helper
            $this->date = \Alaress_Date_Format::newNaturalDate(sprintf("-%d days", $this->days));
        }
    }

    /**
     * Set the retention period. Accepts integer days, numeric string, or null to disable hard deletion.
     * @param int $days
     * @throws SchoolboxException
     */
    public function setRetentionPeriod(int $days): void
    {
        $this->days = $days;
    }

    /**
     * Get the retention period cutoff in database format, or empty string if not set
     * @param int $days
     * @return string
     * @throws SchoolboxException
     */
    public function calculateDate(int $days): string
    {
        if ($this->date) {
            // just return what we calculated before
            return $this->date->getDatabaseDate();
        }
        if (!new ValidRetentionPeriod()->isSatisfiedBy($days)) {
            // we stored an illegal value but tried to continue: die loudly as a safety precaution
            throw new SchoolboxException('Invalid retention period value stored; cannot calculate date cutoff.');
        }

        $this->days = $days;
        $this->date = \Alaress_Date_Format::newNaturalDate(sprintf("-%d days", $this->days));
        return $this->date->getDatabaseDate();
    }

    public function getRetentionDatabase(): string
    {
        return '';
    }

    /**
     * Get the retention period in days, or 0 if not set
     * @return int
     */
    public function getRetentionDays(): int
    {
        return $this->days ?? 0;
    }
}
