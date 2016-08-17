<?php
/**
 * Iterator over a MSSQL result set.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class MssqlResultIterator implements SeekableIterator, Countable
{
    /**
     * @var resource|bool
     */
    protected $result;

    /**
     * Position is semantically stored here, regardless of pointer in underlying result.
     * This implies we must call mssql_data_seek($position) prior to retrieving any data.
     *
     * @var int
     */
    protected $position;

    /**
     * A list of map functions to apply to each result.
     *
     * @var array|callable[]
     */
    protected $maps = [];

    /**
     * Constructor
     *
     * Unfortunately, this can receive the result from mssql_execute() which executes a
     * stored proc. In this case, we can't guarantee that 'false' means error, as it does
     * in almost all other cases, eg mssql_query(). We therefore have to treat both 'true'
     * and 'false' as successful queries that returned empty result sets.
     *
     * @param resource|bool $result
     */
    public function __construct($result)
    {
        if(is_resource($result) && mssql_num_rows($result)){
            $this->result = $result;
        } else {
            $this->result = false;
        }
        $this->position = 0;
    }

    /**
     * Return the current element after applying transformations added via `$this->addMap()`.
     *
     * @return array|null
     */
    public function current()
    {
        if(is_resource($this->result)){
            mssql_data_seek($this->result, $this->position);
            $row = mssql_fetch_assoc($this->result);
            if ($row !== false) {
                foreach ($this->maps as $map) {
                    $row = $map($row);
                }
                return $row;
            }
        }
        return null;
    }

    /**
     * Move forward to next element
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * Return the key of the current element
     *
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid()
    {
        if(!is_resource($this->result) || $this->position < 0 || $this->position >= $this->count() ){
            return false;
        }

        // Intentionally no check for " ! mssql_fetch_assoc($this->result) "
        // If that method fails, the position is still valid, but null should be returned.
        // This ensures a foreach() doesn't bug out prematurely.

        return true;
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Seeks to a position.
     *
     * @var int $position
     * @throw OutOfBoundsException
     */
    public function seek($position)
    {
        // Non-resource results are treated as empty, so seek should always throw
        if(!is_resource($this->result) || $position < 0 || $position >= count($this)){
            throw new OutOfBoundsException(sprintf('Position "%s" is out of bounds', $position));
        }

        // Intentionally distinct from above, as semantics are distinct
        if(!mssql_data_seek($this->result, $position)){
            throw new OutOfBoundsException(sprintf('Unable to seek to position "%s"', $position));
        }

        $this->position = $position;
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        if(is_resource($this->result)){
            return mssql_num_rows($this->result);
        }
        return 0;
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
}
