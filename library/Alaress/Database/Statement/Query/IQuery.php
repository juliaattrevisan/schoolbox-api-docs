<?php

/**
 * Interface for query
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\Query;

use Alaress\Database\Statement\IStatement;

interface IQuery extends IStatement
{
    /**
     * Bind array of input values. This doesn't bind to the PDO statement, just stores the intention in an array.
     * @param array $values
     */
    public function bindInputs($values = []);
}
