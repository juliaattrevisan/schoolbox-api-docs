<?php

/**
 * MSSQL Function call
 *
 * Important note: Function call parameters in T-SQL are strictly ordered- regardless of names used
 * to bind parameters, they must be bound in the correct order or execution will be unpredictable
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\FunctionCall\Dblib;

use Alaress\Database\Statement\FunctionCall\Base;

class FunctionCall extends Base
{
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
        while ($this->fetch() !== false) {
            continue;
        }
        return parent::nextRowset();
    }
}
