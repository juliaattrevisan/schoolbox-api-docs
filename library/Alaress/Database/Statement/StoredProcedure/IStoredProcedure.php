<?php

/**
 * Interface for stored procedure
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure;

use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\Query\IQuery;

interface IStoredProcedure extends IQuery
{
    /**
     * Bind output parameters. This doesn't bind to the PDO statement, just stores the intention in an array.
     * @param array $outputs
     */
    function bindOutputs(&$outputs);

    /**
     * Bind return parameter. This doesn't bind to the PDO statement, just stores the intention in an array.
     * @param mixed &$parameter Return value parameter
     * @throws DatabaseException
     */
    function bindReturnValue(&$parameter);
}
