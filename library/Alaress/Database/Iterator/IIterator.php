<?php

/**
 * Interface for iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator;

use Alaress\Database\Exception;
use Countable;
use Iterator as BaseIterator;

interface IIterator extends BaseIterator, Countable
{
    /**
     * Execute iterator
     *
     * @return bool
     * @throws Exception if the iterator didn't execute for whatever reason
     */
    public function execute();

    /**
     * Move to next rowset in result
     *
     * @return bool
     */
    public function nextRowset();
}
