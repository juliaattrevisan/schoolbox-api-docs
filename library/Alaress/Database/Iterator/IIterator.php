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
use Countable;
use Iterator as BaseIterator;
use PDOStatement;

interface IIterator extends BaseIterator, Countable
{
    /**
     * Execute iterator
     *
     * @return bool
     */
    public function execute();

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