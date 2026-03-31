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
    private ?int $days = null;
    private ?string $databaseDate = null; // database format e.g. '2024-01-01 00:00:00'

    /**
     * Construct with an optional day-count. Use null to indicate "retain indefinitely".
     * Accepts integer, numeric string, or null.
     * @param int|string|null $days
     * @throws SchoolboxException
     */
    public function __construct(int|string|null $days = null)
    {
        $this->setRetentionPeriod($days);
    }

    /**
     * Set the retention period. Accepts integer days, numeric string, or null to disable hard deletion.
     * @param int|string|null $days
     * @throws SchoolboxException
     */
    public function setRetentionPeriod(int|string|null $days): void
    {
        if (null === $days || '' === $days) {
            $this->days = null;
            $this->databaseDate = null;
            return;
        }

        // Normalize numeric string to int
        if (is_string($days)) {
            if (!ctype_digit($days)) {
                throw new SchoolboxException('Retention period string must contain only digits when provided as a string.');
            }
            $days = (int) $days;
        }

        // At this point $days is int
        if (!$this->validate($days)) {
            throw new SchoolboxException('Retention period must be a positive integer number of days.');
        }

        $this->days = $days;

        // Compute database date using the project's date helper
        $natural = \Alaress_Date_Format::newNaturalDate(sprintf("-%d days", $this->days));

        // Defensive: if resulting date is in the future, treat as invalid
        if ($natural->isFuture()) {
            throw new SchoolboxException('Negative retention period supplied. ignoring');
        }

        // Use getDatabase() which returns database format used across the project
        $this->databaseDate = $natural->getDatabase();
    }

    /**
     * Validate a retention period value (must be integer > 0)
     * retention period should be in days, whole number, and greater than 0
     * @param string|int|null $days
     * @return bool
     * @throws SchoolboxException
     */
    public function validate(null|string|int $days): bool
    {
        if (null === $days || '' === $days) {
            return false;
        }

        // Normalise numeric string to int
        if (is_string($days)) {
            if (!ctype_digit($days)) {
                throw new SchoolboxException('Retention period string must contain only digits when provided as a string.');
            }
            $days = (int) $days;
        }

        // At this point $days is int
        if ($days < 0) {
            throw new SchoolboxException('Negative retention period supplied; Retention period must be a positive integer number of days.');
        }
        if ($days === 0) {
            throw new SchoolboxException('Retention period of 0 days supplied');
        }
        return true;
    }

    /**
     * Get the retention period cutoff in database format, or empty string if not set
     * @return string
     */
    public function getRetentionDatabase(): string
    {
        return $this->databaseDate ?? '';
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
