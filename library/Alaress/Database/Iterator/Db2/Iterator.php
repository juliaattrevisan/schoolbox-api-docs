<?php

/**
 * IBM DB2 Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Db2;

use Alaress\Database\Iterator\Base;
use Alaress\Database\Wrapper\Db2\PDOStatement;

class Iterator extends Base
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // DB2 does not implement rewinding or scrollable cursors, just cheating and re-executing the statement.

        $this->rowIndex = -1;

        if ($this->pdoStatement instanceof PDOStatement) {
            $this->pdoStatement->closeCursor();
        }

        $this->execute();
    }
}
