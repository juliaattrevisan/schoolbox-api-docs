<?php

/**
 * Base stored procedure implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure;

use PDO;
use PDOStatement;
use Alaress\Database\Statement\Query\Base as BaseQuery;

abstract class Base extends BaseQuery implements IStoredProcedure {

    /**
     * @inheritdoc
     */
    public function bindOutputs(&$outputs)
    {
        if (is_array($outputs)) {
            foreach ($outputs as $name => $output) {
                $this->parameters['output'][$name] = [
                    'value' => &$output['value'],
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
}