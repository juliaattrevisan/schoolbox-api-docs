<?php
/**
 * Iterator over a MSSQL result set.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class MssqlResultIterator implements SeekableIterator
{
    /**
     * @var resource
     */
    protected $result;

    /**
     * @var array
     */
    protected $currentRow = [];

    /**
     * @var int
     */
    protected $rowNum = 0;

    /**
     * A list of map functions to apply to each result.
     *
     * @var array|callable[]
     */
    protected $maps = [];

    /**
     * Constructor
     *
     * @param resource $result A result from mssql_query
     */
    public function __construct($result)
    {
        $this->result = $result;
    }

    /**
     * Return the current element
     *
     * @return array
     */
    public function current()
    {
        return $this->currentRow;
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        $this->rowNum++;
        $this->loadNextRow();
    }

    /**
     * Return the key of the current element
     *
     * @return int
     */
    public function key()
    {
        return $this->rowNum;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid()
    {
        // Is there a current result?
        $row = $this->currentRow;
        if ($row === false) {
            return false;
        }
        foreach ($this->maps as $map) {
            $row = $map($row);
        }
        $this->currentRow = $row;
        return true;
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        if (gettype($this->result) == 'resource') {
            $this->rowNum = 0;
            $this->seek(0);
            $this->loadNextRow();
        }
    }

    /**
     * Seeks to a position
     *
     * @var int $position
     */
    public function seek($position)
    {
        if (is_resource($this->result)) {
            if (mssql_num_rows($this->result)) {
                if (mssql_data_seek($this->result, $position)) {
                    return;
                }

                /* Tried to access an area beyond the bounds of a
                 * result set that contains data
                 */
                throw new OutOfBoundsException(sprintf('Position "%s" is out of bounds', $position));
            } else {

                /* Empty result set probably shouldn't throw an exception;
                 * just quietly do nothing with it?
                 */
                return;
            }
        }

        /* Not even a resource object, die loudly
         */
        throw new InvalidArgumentException('Invalid result set');
    }

    /**
     * Adds a map function to each row
     *
     * @param  callable $map
     * @return $this
     */
    public function addMap(callable $map)
    {
        $this->maps[] = $map;
        return $this;
    }

    /**
     * Loads next row, and applies transformations added via `$this->addMap()`.
     */
    protected function loadNextRow()
    {
        $row = mssql_fetch_assoc($this->result);
        if ($row !== false) {
            foreach ($this->maps as $map) {
                $row = $map($row);
            }
        }
        $this->currentRow = $row;
    }
}
