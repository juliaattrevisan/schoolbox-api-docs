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
    private ?string $date = null; // database format e.g. '2024-01-01 00:00:00'

    /**
     * Construct with an optional day-count. Use null to indicate "retain indefinitely".
     * Accepts integer, numeric string, or null.
     * @param int|null $days
     * @throws SchoolboxException
     */
    public function __construct(private int|null $days = null)
    {
    }

    /**
     * Set the retention period. Accepts integer days, or null to disable hard deletion.
     * @param null|int $days
     * @return self
     * @throws SchoolboxException
     */
    public function setRetentionPeriod(null|int $days): self
    {
        $this->days = $days;
        return $this;
    }

    /**
     * Get the retention period cutoff in database format, or empty string if not set
     * @param int $days
     * @return string
     * @throws SchoolboxException
     */
    public function calculateDate(int $days): string
    {
        // if we haven't cached the days (no override)
        // we validate and cache that now
        if (!$this->days) {
            if (!new ValidRetentionPeriod()->isSatisfiedBy($days)) {
                // we stored an illegal value but tried to continue: die loudly as a safety precaution
                throw new SchoolboxException('Invalid retention period value stored; cannot calculate date cutoff.');
            }

            $this->days = $days;
        }

        // cache the db date, so we don't need to keep calling
        if ($this->date === null) {
            $this->date = \Alaress_Date_Format::newNaturalDate(sprintf("-%d days", $this->days))->getDatabaseDate();
        }

        // return the cached date
        return $this->date;
    }

    /**
     * Get the retention period in days, or 0 if not set
     * @return null|int
     */
    public function getRetentionDays(): ?int
    {
        return $this->days;
    }
}
