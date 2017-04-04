<?php

/**
 * MSSQL Stored Procedure
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure\Sqlsrv;

use Alaress\Database\Statement\StoredProcedure\Base;

class StoredProcedure extends Base
{
    /**
     * @inheritdoc
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
                $parameterPlaceholders .= "@{$name} = :{$name}";
            }
        }

        if (isset($this->parameters['output'])) {
            foreach ($this->parameters['output'] as $name => $parameter) {
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= "@{$name} = :{$name}";
            }
        }

        // Cobble everything together into SQL statement

        $sql = "EXEC";

        if (isset($this->parameters['return'])) {
            $sql .= " :return =";
        }

        // Add the function name and its parameter placeholders

        $sql = "{$sql} {$this->name} {$parameterPlaceholders}";

        return $sql;
    }
}
