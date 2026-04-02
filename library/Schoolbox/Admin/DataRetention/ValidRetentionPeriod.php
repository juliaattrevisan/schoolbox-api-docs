<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

/**
 * Specification-style validator for CLI --retention-period values.
 */
class ValidRetentionPeriod
{
    /**
     * Returns true only when the value represents a positive
     * integer number of days.
     */
    public function isSatisfiedBy(int|string $days): bool
    {
        if ('' === $days) {
            return false;
        }

        // Normalise numeric string to int
        if (is_string($days)) {
            if (!ctype_digit($days)) {
                throw new SchoolboxException('Retention period string must contain only digits when provided as a string.');
            }
            $days = (int)$days;
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
}
