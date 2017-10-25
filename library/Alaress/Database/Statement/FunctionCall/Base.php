<?php

/**
 * Base function call implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\FunctionCall;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\Query\Base as BaseQuery;
use PDO;
use PDOStatement;

class Base extends BaseQuery implements IFunctionCall
{

    /** @var string $name Function name */
    protected $name;

    /**
     * Function call constructor
     *
     * @param IBroker $broker Database wrapper that this object belongs to;
     * @param string $name Name of function call to execute
     * @throws DatabaseException
     */
    public function __construct($broker, $name)
    {
        parent::__construct($broker, null);

        // Unlike the standard query we do not prepare the statement in the constructor as we need to construct the
        // query dynamically based on the parameters that are passed. So for now we just store the broker and the
        // name of the function we intend to execute.

        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function bindOutputs(&$outputs)
    {
        if (is_array($outputs)) {
            foreach ($outputs as $name => $output) {
                $this->parameters['output'][$name] = [
                    'value' => &$output['value'],

                    // Bit-wise logic is intended here, output parameter type requires
                    // PDO::PARAM_INPUT_OUTPUT to be OR'd with the actual type, eg:
                    //
                    // 'type' => (PDO::PARAM_STR | PDO::PARAM_INPUT_OUTPUT)
                    //
                    // This just means we don't have to remember to do that, and it
                    // doesn't break anything if we do remember to

                    'type' => $output['type'] | PDO::PARAM_INPUT_OUTPUT,
                    'length' => $output['length']
                ];
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function bindReturnValue(&$parameter)
    {
        // Store reference to the output parameter

        $this->parameters['return'] = [
            'value' => &$parameter['value'],
            'type' => $parameter['type'],
            'length' => $parameter['length']
        ];
    }

    /**
     * @inheritdoc
     */
    protected function bindStatement() {

        /** @var PDOStatement $statement */
        $statement = $this->statement;

        // Bind inputs
        parent::bindStatement();

        // Bind output parameters

        if (isset($this->parameters['output'])) {
            foreach ($this->parameters['output'] as $name => &$output) {
                $statement->bindParam(
                    $name,
                    $output['value'],
                    $output['type'] | PDO::PARAM_INPUT_OUTPUT,
                    $output['length']
                );
            }
        }

        // Bind return value

        if (isset($this->parameters['return'])) {
            $statement->bindParam(
                'return',
                $this->parameters['return']['value'],
                $this->parameters['return']['type'] | PDO::PARAM_INPUT_OUTPUT,
                $this->parameters['return']['length']
            );
        }
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

        $sql = "SELECT";

        if (isset($this->parameters['return'])) {
            $sql .= ":return =";
        }

        // Add the function name and its parameter placeholders

        return "{$sql} {$this->name}({$parameterPlaceholders})";
    }
}
