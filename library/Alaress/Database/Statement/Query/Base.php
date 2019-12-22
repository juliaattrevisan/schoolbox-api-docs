<?php

/**
 * Base query implementation
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

    /**
     * Database statement constructor
     *
     * @param IBroker $broker Database broker that this query belongs to
     * @param string  $sql    SQL query string to be executed
     */
    public function __construct($broker, $sql)
    {
        $this->broker = $broker;
        $this->sql    = $sql;
    }

    /**
     * @inheritdoc
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Bind array of input values. This doesn't bind to the PDO statement, just stores the intention in an array.
     *
     * @param array $values
     *
     * @return self
     */
    public function bindInputs($values = [])
    {
        if (!isset($this->parameters['input'])) {
            $this->parameters['input'] = [];
        }

        foreach ($values as $key => $value) {
            // Numeric keys (for non-named parameters, i.e. ?) need to be mangled here.
            // * PDOStatement::execute($params) expects a 0-indexed array
            // * PDOStatement::bindValue($param, $value) expects $param to be 1-indexed
            // This method was called with the expectation of 0-indexes (as $params was passed directly),
            // but it requires 1-indexes, as each param is bound separately.
            // It's tempting to just ban non-named parameters,
            // but they are more concise than named ones if you just want to pass a single param.
            if (is_int($key) === true) {
                ++$key;
            }

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
                $this->parameters['input'][$key] = [
                    'value'  => $value['value'],
                    'type'   => isset($value['type']) ? $this->getDataType($value, $value['type']) : null,
                    'length' => isset($value['length']) ?

                        // Parameters length was specified, use it

                        $value['length'] :

                        // Length was not specified, if the parameter is a string, use its length- otherwise pass NULL

                        ($this->getDataType($value, $value['type']) == PDO::PARAM_STR ? strlen($value['value']) : null),
                ];
            } else {
                $this->parameters['input'][$key] = [
                    'value'  => $value,
                    'type'   => $this->getDataType($value),
                    'length' => isset($value['length']) ?

                        // Parameters length was specified, use it

                        $value['length'] :

                        // Length was not specified, if the parameter is a string, use its length- otherwise pass NULL

                        ($this->getDataType($value) == PDO::PARAM_STR ? strlen($value) : null),
                ];
            }
        }

        return $this;
    }

    /**
     * This is just an internal function that actually binds all of the input/output/return values we have stored in our
     * array to the PDO statement
     */
    protected function bindStatement()
    {
        /** @var PDOStatement $statement */
        $statement = $this->statement;

        if (isset($this->parameters['input'])) {
            foreach ($this->parameters['input'] as $name => $input) {
                if (isset($input['type'])) {
                    if ($input['type'] == PDO::PARAM_LOB) {
                        $statement->bindParam($name, $input['value'], PDO::PARAM_LOB, 0);
                    } else {
                        $statement->bindValue($name, $input['value'], $input['type']);
                    }
                } else {
                    $statement->bindValue($name, $input['value']);
                }
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
