<?php

/**
 * MySQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Mysql;

use Alaress\Database\Iterator\Base;
use PDOStatement;

class Iterator extends Base
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // MySQL does not implement rewinding or scrollable cursors, just cheating and re-executing the statement.

        $this->rowIndex = -1;

        if ($this->pdoStatement instanceof PDOStatement) {
            $this->pdoStatement->closeCursor();
        }

        $this->execute();
    }
}
