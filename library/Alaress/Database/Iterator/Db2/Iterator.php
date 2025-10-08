<?php

/**
 * IBM DB2 Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Iterator\Db2;

use Alaress\Database\Iterator\Base;
use PDOStatement;

class Iterator extends Base
{
    /**
     * @inheritdoc
     */
    public function rewind(): void
    {
        // DB2 does not implement rewinding or scrollable cursors, just cheating and re-executing the statement.

        $this->rowIndex = -1;

        if ($this->pdoStatement instanceof PDOStatement) {
            $this->pdoStatement->closeCursor();
        }

        $this->executeToCurrentRowset();
    }
}
