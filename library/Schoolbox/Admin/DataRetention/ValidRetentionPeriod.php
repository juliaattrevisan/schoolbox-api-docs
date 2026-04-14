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
     * Validates a CLI retention period value.
     * Accepts a positive integer or a digit-only numeric string.
     * Returns false for empty, zero, negative, or non-numeric values.
     * NULL is special, and valid, indicating no retention period (i.e. keep indefinitely).
     *
     * @param null|int|string $days
     * @return bool
     */
    public function isSatisfiedBy(null|int|string $days): bool
    {
        if (null === $days) {
            return true;
        }

        if ('' === $days) {
            return false;
        }

        // Normalise numeric string to int
        if (is_string($days)) {
            if (!ctype_digit($days)) {
                return false;
            }
            $days = (int)$days;
        }

        // At this point $days is int
        if ($days < 0) {
            return false;
        }
        if ($days === 0) {
            return false;
        }
        return true;
    }
}
