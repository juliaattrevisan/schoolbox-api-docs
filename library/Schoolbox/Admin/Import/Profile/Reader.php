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
     * Field delimiter for the CSV file
     *
     * @var string
     */
    private const DELIMITER = ',';


    /**
     * Field enclosure for the CSV file
     *
     * @var string
     */
    private const ENCLOSURE = '"';


    /**
     * Field escaper for the CSV file
     *
     * @var string
     */
    private const ESCAPE = '\\';


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
     * The header row
     * A stack of strtolower'd column names from the first row in csv
     *
     * @var array
     */
    private $columnList = [];


    /**
     * A stack of expected column names e.g.
     * ['ID', 'Firstname', 'Lastname' ...]
     *
     * @var array|null
     */
    private $expectedColumns = [];


    /**
     * An array of $expectedColumns as keys, but each key is strtolower'd
     *
     * @var array
     */
    private $template = [];


    /**
     * Reader constructor.
     *
     * Check that the filename/resource is valid
     * Die loudly if it's not
     *
     * @param resource|string  $filename   a path to file or a fopen'd file resource
     * @param array|null $expectedColumns  an optional set of expected columns
     * @throws \RuntimeException
     */
    public function __construct($filename, ?array $expectedColumns = null)
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
         * using case-insensitive matching; take the expected columns and strtolower them
         */
        if (null !== $expectedColumns) {
            $this->expectedColumns = $expectedColumns;

            $this->template = array_fill_keys(array_map('strtolower', $expectedColumns), '');
        }

        /* Find the column arrangement from the csv
         */
        $this->columnList = array_map('strtolower', $this->readHeaderRow());
    }


    /**
     * Read the header row from the csv and remember it
     */
    private function readHeaderRow()
    {
        $this->rewind();

        return $this->currentRow;
    }


    /**
     *
     * Loads the next row from the CSV file.
     *
     * @return null|array|bool
     */
    private function loadRow()
    {
        return fgetcsv($this->fp, 0, self::DELIMITER, self::ENCLOSURE, self::ESCAPE);
    }


    /**
     * Return the current element
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        /* Read a row from the csv
         *
         * The row, in its worst acceptable configuration, has an out-of-order subset of columns
         * with non-identical headings due to case insensitivity
         *
         * The data row from the csv will be in the columns denoted by the file's header row
         * Make an associative array using array_combine
         */
        $combined = array_combine($this->columnList, $this->currentRow);

        /* array_merge(1, 2) so that the data from the csv is in the order denoted by the expected columns
         */
        $merged = array_merge($this->template, $combined);

        /* array_combine again to use the expected columns' case, not the strtolowered version
         * the csv format, though wild, will transparently (and magically) look like it was in the right
         * format the whole time
         */
        return array_combine($this->expectedColumns, array_values($merged));
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
        $this->currentRow = $this->loadRow();
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
    public function rewind(): void
    {
        /* Reset all the vars
         */
        $this->cleanup();

        /* Reopen the file
         */
        if (!\is_resource($this->fp)) {
            $this->fp = fopen($this->filename, 'r');
        }

        /* Read that first row
         */
        $this->next();
    }


    /**
     * Close files and reset properties
     */
    private function cleanup() {
        if (\is_resource($this->fp) && $this->filename) {
            fclose($this->fp);
            unset($this->fp);
        }

        $this->currentKey = 0;
        $this->currentRow = false;
    }
}

