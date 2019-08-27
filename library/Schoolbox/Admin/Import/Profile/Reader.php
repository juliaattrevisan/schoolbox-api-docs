<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

/**
 * Class Reader
 *
 * Import a csv and match optionally coerce its row data
 * to the columns specified via constructor
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Reader implements \Iterator
{

    /**
     * @var int
     */
    private $currentKey = 0;


    /**
     * The current row that came from the csv
     *
     * array if fgetcsv succeeded
     * false if fgetcsv() didn't succeed
     *
     * @var bool|array
     */
    private $currentRow = false;


    /* This class can read from a filename or a resource
     */

    /**
     * The file name to read from.
     *
     * @var null|string
     */
    private $filename;


    /**
     * The filepointer to read from
     *
     * @var null|resource
     */
    private $fp;


    /**
     * A stack of expected column names e.g.
     * ['ID', 'Firstname', 'Lastname' ...]
     *
     * @var array|null
     */
    private $expectedColumns = [];


    /**
     * Reader constructor.
     *
     * Check that the filename/resource is valid
     * Die loudly if it's not
     *
     * @param            $filename         a $filename or a fopen'd file resource
     * @param array|null $expectedColumns  an optional set of expected columns
     * @throws \RuntimeException
     */
    public function __construct($filename, ?array $expectedColumns)
    {
        if (\is_resource($filename)) {

            /* Check if it's a fopen'd resource
             */
            $this->filename = null;
            $this->fp       = $filename;
        } elseif (is_readable($filename)) {

            /* Otherwise maybe it's a path to a filename
             */
            $this->filename = $filename;
        } else {

            /* Everything else is unknown; exit loudly
             */
            throw new \RuntimeException(sprintf('File "%s" not found, or is not readable!', $filename));
        }

        /* If expected columns were provided we'll need to map rows to expected columns
         * using case-insensitive matching
         */
        if (null !== $expectedColumns) {
            $this->expectedColumns = $expectedColumns;
        }
    }


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

    /**
     * Return the current element
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        // TODO: Implement current() method.
    }

    /**
     * Move forward to next element
     * @link  https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next(): void
    {
        $this->currentKey++;
        // Loadrow() equivalent
        // $this->currentRow
    }

    /**
     * Return the key of the current element
     * @link  https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->currentKey;
    }

    /**
     * Checks if current position is valid
     * @link  https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid(): bool
    {
        return $this->currentRow !== false;
    }

    /**
     * Rewind the Iterator to the first element
     * @link  https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        // TODO: Implement rewind() method.
    }
}
