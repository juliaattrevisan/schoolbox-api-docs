<?php

/**
 * MSSQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Sqlsrv;

use Alaress\Database\Iterator\Base;
use PDOStatement;

class Iterator extends Base
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        $this->rowIndex = -1;

        if ($this->pdoStatement instanceof PDOStatement) {
            $this->pdoStatement->closeCursor();
        }

        $this->execute();
    }
}
