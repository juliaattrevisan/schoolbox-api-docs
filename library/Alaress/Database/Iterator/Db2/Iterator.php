<?php

/**
 * IBM DB2 Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Db2;

use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Iterator\Base;

class Iterator extends Base implements IIterator
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // MySQL does not implement rewinding or scrollable cursors, just cheating and re-executing the statement.

        $this->rowIndex = -1;
        $this->execute();
    }
}