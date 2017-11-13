<?php

/**
 * Base stored procedure implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\Query\Base as BaseQuery;
use PDO;

class Base extends BaseQuery implements IStoredProcedure
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
    protected function bindStatement()
    {
        parent::bindStatement();
        $this->internalBindOutputs();
        $this->internalBindReturnValue();
    }

    /**
     * Bind a single output parameter.
     *
     * @param string $name
     * @param &array $param
     */
    protected function internalBindOutputParam($name, &$param)
    {
        $this->statement->bindParam(
            $name,
            $param['value'],
            $param['type'] | PDO::PARAM_INPUT_OUTPUT,
            $param['length']
        );
    }

    /**
     * Internal method for binding output values to stored procedures.
     */
    protected function internalBindOutputs()
    {
        if (isset($this->parameters['output'])) {
            foreach ($this->parameters['output'] as $name => &$output) {
                $this->internalBindOutputParam($name, $output);
            }
            unset($output);
        }
    }

    /**
     * Internal method for binding return values to stored procedures.
     */
    protected function internalBindReturnValue()
    {
        if (isset($this->parameters['return'])) {
            $this->internalBindOutputParam('return', $this->parameters['return']);
        }
    }
}
