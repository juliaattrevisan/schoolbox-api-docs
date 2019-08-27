<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;


class Reader
{
    /* Implement Iterator interface
     */

    /* Take a $filename or a file resource as first arg
     * Take an optional set of expected columns as second arg
     *
     * If expected columns were provided we'll need to map rows to expected columns
     * using case-insensitive matching
     */

    /* Check that the filename/resource is valid
     * Die loudly if it's not
     */

    /* Read the header row from the csv and remember it
     */

    /* Read a row from the csv
     *
     * The row, in its worst acceptable configuration, has an out-of-order subset of columns
     * with non-identical headings due to case insensitivity
     *
     * 1. Take the expected columns and strtolower them
     *
     * 2. The data row from the csv will be in the columns denoted by the file's header row
     * Make an associative array using array_combine
     *
     * 3. array_merge(1, 2) so that the data from the csv is in the order denoted by the expected columns
     *
     * 4. array_combine again to use the expected columns' case, not the strtolowered version
     * the csv format, though wild, will transparently (and magically) look like it was in the right
     * format the whole time
     */

}
