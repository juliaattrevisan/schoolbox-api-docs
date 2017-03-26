<?php

/**
 * MSSQL Stored Procedure
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure\Sqlsrv;

use Alaress\Database\Broker\Sqlsrv\Broker;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use Alaress\Database\Statement\StoredProcedure\Base;

class StoredProcedure extends Base implements IStoredProcedure
{
    /** @var Broker $broker */
    protected $broker;

    /** @var string Stored procedure name */
    private $name;

    /**
     * Stored procedure constructor
     *
     * @param Broker $broker Database wrapper that this object belongs to;
     * @param string $name Name of stored procedure to execute
     * @throws DatabaseException
     */
    public function __construct($broker, $name)
    {
        // Unlike the standard query we do not prepare the statement in the constructor as we need to construct the
        // query dynamically based on the parameters that are passed. So for now we just store the broker and the
        // name of the procedure we intend to execute.

        $this->broker = $broker;
        $this->name = $name;
    }

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
