<?php

/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

/**
 * Generic utilities for rubrics, that are used in various places but don't
 * really belong in any one place.
 */
class Util
{
    /**
     * Normalizes a name:
     * * Leading and trailing spaces are stripped
     * * Multiple consecutive spaces are converted to single spaces
     * * Convert to UTF-8 lowercase
     *
     * This is used to ensure that a capability or indicator with a similar name
     * to an existing one in the same place cannot be created.
     *
     * @param string $name
     * @return string
     * @see /library/Schoolbox/Assessment/Rubric/templates/vue/rb-rubric-grid.vue::normaliseForComparison (must use the same logic)
     */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('`\s+`', ' ', trim($name)), 'UTF-8');
    }
}
