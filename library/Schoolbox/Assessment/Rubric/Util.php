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
     *
     * Before saving a rubric capability or indicator, this method is called on
     * its name. This attempts to ensure that meaningless differences in white
     * space don't cause a new capability or indicator to be created.
     *
     * @param string $name
     * @return string
     */
    public static function normalizeName(string $name): string
    {
        return preg_replace('`\s+`', ' ', trim($name));
    }
}
