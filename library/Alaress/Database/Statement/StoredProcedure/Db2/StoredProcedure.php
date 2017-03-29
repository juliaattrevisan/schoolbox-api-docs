<?php

/**
 * IBM DB2 Stored Procedure
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure\Db2;

use Alaress\Database\Statement\StoredProcedure\Base;

class StoredProcedure extends Base
{
    /**
     * @inheritdoc
     *
     * TODO: This is the SQLSRV code, need to work out how to execute a stored procedure in DB2 and correct the SQL syntax
     */
    public function getSql()
    {
        // Create input/output parameter placeholders

        $parameterPlaceholders = '';

        if (isset($this->parameters['input'])) {
            foreach ($this->parameters['input'] as $name => $value) {
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= ":{$name}";
            }
        }

        if (isset($this->parameters['output'])) {
            foreach ($this->parameters['output'] as $name => $parameter) {
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= ":{$name}";
            }
        }

        // Cobble everything together into SQL statement

        $sql = "CALL";

        if (isset($this->parameters['return'])) {
            $sql .= " :return =";
        }

        // Add the function name and its parameter placeholders

        return "{$sql} {$this->name} {$parameterPlaceholders}";
    }
}