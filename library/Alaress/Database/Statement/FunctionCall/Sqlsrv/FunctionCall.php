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

namespace Alaress\Database\Statement\FunctionCall\Sqlsrv;

use Alaress\Database\Broker\Sqlsrv\Broker;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\FunctionCall\Base;

class FunctionCall extends Base implements IFunctionCall
{
    /** @var Broker $broker */
    protected $broker;

    /** @var string Function call name */
    private $name;

    /**
     * Function call constructor
     *
     * @param Broker $broker Database wrapper that this object belongs to;
     * @param string $name Name of function call to execute
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
    public function execute($values = null) {

        // We need to create the function call SQL dynamically based on the parameters we chose to bind, then we can
        // just execute as per normal

        $this->sql = $this->createFunctionCallSQL();
        return parent::execute($values);
    }

    /**
     * @inheritdoc
     */
    public function executeIterator($values = null) {

        // We need to create the function call SQL dynamically based on the parameters we chose to bind, then we can
        // just execute as per normal

        $this->sql = $this->createFunctionCallSQL();
        return parent::executeIterator($values);
    }

    /**
     * This creates the SQL to execute a function call
     *
     * @return string
     * @throws DatabaseException
     */
    private function createFunctionCallSQL()
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

        $this->sql = "SELECT ";

        if (isset($this->parameters['return'])) {
            $this->sql .= ":return = ";
        }

        // Add the function name and its parameter placeholders

        $this->sql .= "{$this->name}({$parameterPlaceholders})";
        return $this->sql;
    }
}
