<?php

/**
 * Base query implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\Query;

use Alaress\Database\Broker\IBroker;
use PDO;
use PDOStatement;
use Alaress\Database\Statement\Base as BaseStatement;

class Base extends BaseStatement
{
    /** @var string $sql */
    protected $sql;

    /** @var IBroker $broker */
    protected $broker;

    /**
     * Database statement constructor
     *
     * @param IBroker $broker Database broker that this query belongs to
     * @param string $sql SQL query string to be executed
     */
    function __construct($broker, $sql) {
        $this->broker = $broker;
        $this->sql = $sql;
    }

    /**
     * @inheritdoc
     */
    public function getSql() {
        return $this->sql;
    }

    /**
     * Bind array of input values. This doesn't bind to the PDO statement, just stores the intention in an array.
     * @param array $values
     */
    public function bindInputs($values = [])
    {
        if (!isset($this->parameters['input'])) {
            $this->parameters['input'] = [];
        }

        foreach ($values as $key => $value) {

            // Inputs could in a simple key/value pair, or could be in a key/array pair (which contains the
            // optional value/type/length keys). Probably makes more sense with an example...
            //
            // [
            //      'firstname' => 'rachel',
            //      'lastname' => 'rhodes'
            // ]
            //
            // Or...
            //
            // [
            //      'firstname' => [
            //          'value' => 'rachel',
            //          'type' => PDO::PARAM_STR,
            //          'length' => 50
            //      ],
            //      'lastname' => [
            //          'value' => 'rhodes',
            //          'type' => PDO::PARAM_STR,
            //          'length' => 50
            //      ]
            // ]
            //
            // Make sense? Nope... oh well...
            //
            // Another thing here is that we always make sure there is a data type specified using the getDataType() helper
            // function, this ensure that we get around the issue with using parameters for special things like 'limit' in
            // queries, which breaks if you don't specify the type as PDO::PARAM_INT

            if (is_array($value)) {

                $this->parameters['input'][$key] = [];
                $this->parameters['input'][$key] = [
                    'value' => $value['value'],
                    'type' => isset($value['type']) ? $this->getDataType($value, $value['type']) : null,
                    'length' => isset($value['length']) ? $value['length'] : null
                ];

            } else {

                $this->parameters['input'][$key] = [
                    'value' => $value,
                    'type' => $this->getDataType($value),
                    'length' => null
                ];
            }
        }
    }

    /**
     * This is just an internal function that actually binds all of the input/output/return values we have stored in our
     * array to the PDO statement
     */
    protected function bindStatement()
    {
        /** @var PDOStatement $statement */
        $statement = $this->statement;

        // Bind input parameters

        if (isset($this->parameters['input'])) {
            foreach ($this->parameters['input'] as $name => $input) {
                $statement->bindValue(
                    $name,
                    $input['value']
                );
            }
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Helper function to get data type of parameter if none was supplied
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    private function getDataType($value, $type = null)
    {
        if (empty($type)) {
            switch (true) {
                case is_int($value):
                    return PDO::PARAM_INT;
                case is_bool($value):
                    return PDO::PARAM_BOOL;
                case is_null($value):
                    return PDO::PARAM_NULL;
                default:
                    return PDO::PARAM_STR;
            }
        }

        return $type;
    }
}