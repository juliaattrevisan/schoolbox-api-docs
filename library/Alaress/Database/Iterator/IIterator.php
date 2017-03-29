<?php

/**
 * Interface for iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\IStatement;
use Iterator as BaseIterator;
use PDOStatement;

interface IIterator extends BaseIterator
{
    /**
     * Execute iterator
     *
     * @return bool
     */
    public function execute();

    /**
     * Return the current element
     *
     * @return array|bool|object
     * @throws DatabaseException
     */
    public function current();

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    public function next();

    /**
     * Return the key of the current element
     *
     * @return mixed scalar on success, or null on failure.
     */
    public function key();

    /**
     * Checks if current position is valid
     *
     * @return boolean The return value will be casted to boolean and then evaluated.
     */
    public function valid();

    /**
     * Rewind the Iterator to the first element
     *
     * @return void
     */
    public function rewind();

    /**
     * Move to next rowset in result
     *
     * @return bool
     */
    public function nextRowset();

    /**
     * Returns last error message this statement generated
     *
     * @return string
     */
    public function errorInfo();

    /**
     * Returns last error message this statement generated
     *
     * @return string
     */
    public function errorCode();
}