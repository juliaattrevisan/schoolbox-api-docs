<?php

/**
 * MSSQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Dblib;

use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Iterator\Base;

class Iterator extends Base implements IIterator
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // Have disabled rewinding due to performance issues with cursors being enabled
    }

    /**
     * @return bool
     */
    public function nextRowset()
    {
        // Drain this iterator
        while ($this->valid()) {
            $this->next();
        }
        return parent::nextRowset();
    }
}
