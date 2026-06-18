<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

class ValidCsv
{
    /**
     * For the given file contents $input, figure out if it's valid rubric csv contents.
     *
     * Rubric CSVs have a distinct signature: its A1 and B1 cells have values that are idiosyncratic and shouldn't appear
     * anywhere else in the schoolbox universe which lets us use this conclusively.
     *
     * Further analysis can be added to ensure not only that the csv was a rubric csv, but that its contents were also
     * meaningful: however, the depth of that meaningfulness might be too strict for how the schools naturally operate.
     *
     * @param string|null $input
     * @return bool
     */
    public function isSatisfiedBy(?string $input): bool
    {
        /* Primary checks
         * These must pass in order for us to even THINK about importing csv contents
         */

        /* null input: exit early
         */

        /* empty string/whitespace only: exit early
         */

        /* A1 and B1 cells do not contain unique rubric signature: exit early
         */

        /* only one row detected: exit early
         */

        /* Secondary checks
         * These are more nuanced "requirements" that might make things too difficult for actual schools on a daily
         * basis as it thwarts what they are familiar with doing. Add (or remove) secondary checks as needed.
         */

        /* At least one capability is mentioned
         */

        /* At least one indicator is mentioned
         */

        /* At least one of the following is mentioned:
         *     - A cell with descriptor text
         *     - A cell with descriptor points
         *     - Two cells to fulfil both of the above
         */
    }
}
