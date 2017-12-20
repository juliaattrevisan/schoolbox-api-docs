<?php

/**
 * MSSQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Dblib;

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

    /**
     * DBLib demands that the current rowset be fully iterated before moving to
     * the next rowset, otherwise the below error gets thrown:
     *
     * `General error: PDO_DBLIB: dbresults() returned FAIL`
     *
     * Iterate transparently in this implementation, instead of shoehorning in
     * `fetch()` loops whenever we use `nextRowset()`.
     *
     * @return bool
     */
    public function nextRowset()
    {
        while ($this->valid()) {
            $this->next();
        }
        return parent::nextRowset();
    }
}
