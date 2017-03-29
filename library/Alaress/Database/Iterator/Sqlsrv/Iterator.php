<?php

/**
 * MSSQL Iterator
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Iterator\Sqlsrv;

use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Alaress\Database\Iterator\Base;
use PDO;

class Iterator extends Base implements IIterator
{
    /**
     * @inheritdoc
     */
    public function rewind()
    {
        // Only waste our time rewinding if we're not already at the start of the results

        if ($this->rowIndex > 0) {

            if (!$this->statement instanceof IStoredProcedure) {

                // This works for rewinding plain old boring queries, but for some reason doesn't work with stored procedures

                $this->rowIndex = 0;
                $this->currentRow = $this->pdoStatement->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_FIRST);

            } else {

                // This is the only way I could get rewinding to work with stored procedures reliably. Not exactly elegant.
                // If anyone can find another way- please feel free to rewrite this for me- at this point I totally can't
                // be fudged.

                // FYI- the MySQL hack which is much tidier (shown below) blows up randomly with various PDO_SQLSRV errors
                //
                // $this->rowIndex = -1;
                // $this->execute();

                $iterator = $this->statement->executeIterator();
                $this->currentRow = $iterator->current();
                $this->rowIndex = 0;
            }
        }
    }
}