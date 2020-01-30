<?php

/**
 * PDOStatement Wrapper for IBM DB2
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Wrapper\Db2;

use Alaress\Database\Exception;
use PDO as BasePDO;
use PDOStatement as BasePDOStatement;

class PDOStatement extends BasePDOStatement
{
    /** @var resource $connection */
    protected $connection;

    /** @var resource $statement */
    protected $statement;

    /** @var string $sql */
    protected $sql;

    /** @var int $fetchStyleDefault */
    protected $fetchStyleDefault = BasePDO::FETCH_ASSOC;

    /** @var array $bindings */
    protected $bindings = [];

    /**
     * Constructor
     * @param resource $connection Database connection resource associated with the statement
     * @param string $sql SQL statement that was being prepared
     * @param null|int $fetchStyleDefault Default fetch style
     */
    public function __construct($connection, $sql, $fetchStyleDefault = BasePDO::FETCH_ASSOC)
    {
        $this->sql = $sql;
        $this->connection = $connection;
        $this->fetchStyleDefault = $fetchStyleDefault;
    }

    /**
     * @inheritdoc
     *
     * This is complaining about the declaration being incompatible with the interface. But the interface
     * on the vagrant PHP seems to be different from whatever storm is expecting
     *
     * Storm expects: public function execute(array $input_parameters = null)
     */
    public function execute($input_params = null)
    {
        if (is_array($input_params) && count($input_params) > 0) {

            // Use the supplied parameters rather than the ones previously bound

            $parameters = $input_params;

        } else {

            // No parameters were passed, extract named parameters from the SQL statement and use the values
            // that should have been previously bound using the Db2/PDOStatement functions.

            $parameters = preg_match_all('#:([A-Za-z0-9-_]+)#', $this->sql, $matches) ? $matches[1] : null;

        }

        // Replace the named parameters in the SQL string with anonymous '?' placeholder

        $sql = preg_replace('#:[A-Za-z0-9-_]+#', '?', $this->sql);

        // Create the prepared statement from the SQL with anonymous placeholders

        $this->statement = db2_prepare($this->connection, $sql, ['cursor' => DB2_SCROLLABLE]);

        // The commented code below *should* work for binding both input and output parameters as far as I can tell-
        // but I am receiving "Describe Param Failed" on my test database. Disabling this for now, which limits us
        // to using input type parameters only for now.

        // Bind the parameters in the order they appeared in the query

        $currentParameterIndex = 0;

        $variables = [];

        if (is_array($parameters)) {
            foreach ($parameters as $parameterName) {

                // We need to slot the values of the parameters into a numerically 0-indexed array that matches the
                // order of the parameters used in the query

                if(isset($this->bindings[$parameterName]["value"])) {
                    $variables[$currentParameterIndex] = $this->bindings[$parameterName]["value"];
                }
                elseif(isset($this->bindings[':'.$parameterName]["value"])) {
                    $variables[$currentParameterIndex] = $this->bindings[':'.$parameterName]["value"];
                }
                else {
                    throw new Exception("Required parameter '{$parameterName}' was not supplied.'");
                }

                $currentParameterIndex++;
            }
        }

        return db2_execute($this->statement, $variables);
    }

    /**
     * @inheritdoc
     */
    public function fetch($fetch_style = null, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        if ($fetch_style == null) {
            $fetch_style = $this->fetchStyleDefault;
        }

        switch ($fetch_style) {
            case BasePDO::FETCH_ASSOC:
                return db2_fetch_assoc($this->statement);
                break;

            case BasePDO::FETCH_BOTH:
                return db2_fetch_both($this->statement);
                break;

            case BasePDO::FETCH_OBJ:
                return db2_fetch_object($this->statement);
                break;
        }

        throw new Exception("Requested fetch type not supported by DB2 wrapper.");
    }

    /**
     * @inheritdoc
     */
    public function bindParam($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = null, $driver_options = null)
    {
        if ($data_type & PDO::PARAM_INPUT_OUTPUT) {
            $parameterType = DB2_PARAM_INOUT;
        } else {
            $parameterType = DB2_PARAM_IN;
        }

        $this->bindings[$parameter] = [
            'type' => $parameterType,
            'value' => &$variable
        ];
    }

    /**
     * @inheritdoc
     */
    public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
    {
        $this->bindings[$parameter] = [
            'type' => DB2_PARAM_IN,
            'value' => $value
        ];
    }

    /**
     * @inheritdoc
     */
    public function rowCount()
    {
        return db2_num_rows($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($column_number = 0)
    {
        $row = db2_fetch_both($this->statement);

        if (!$row) {
            return false;
        }

        if (!isset($row[$column_number])) {
            throw new Exception("The requested column '{$column_number}' could not be located in the results.");
        }

        return $row[$column_number];
    }

    /**
     * @inheritdoc
     *
     * This is complaining about the declaration being incompatible with the interface. But the interface
     * on the vagrant PHP seems to be different from whatever storm is expecting
     *
     * Storm expects: public function fetchAll($fetch_style = null, $fetch_argument = null, array $ctor_args = array())
     */
    public function fetchAll($how = null, $class_name = null, $ctor_args = null)
    {
        $result = [];

        while ($row = $this->fetch($how)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return db2_stmt_error($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return db2_stmt_errormsg($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($attribute, $value)
    {
        return db2_set_option($this->statement, [$attribute => $value], 0);
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($attribute)
    {
        return db2_get_option($this->statement, $attribute);
    }

    /**
     * @inheritdoc
     */
    public function columnCount()
    {
        return db2_num_fields($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function setFetchMode($mode, $params = null)
    {
        $this->fetchStyleDefault = $mode;
    }

    /**
     * @inheritdoc
     */
    public function nextRowset()
    {
        db2_next_result($this->statement);
    }

    /**
     * @inheritdoc
     */
    public function closeCursor()
    {
        db2_free_stmt($this->statement);
    }

    // These methods haven't been implemented because we don't use them (and I'm too lazy to write them)

    /**
     * @inheritdoc
     *
     * This is complaining about the declaration being incompatible with the interface. But the interface
     * on the vagrant PHP seems to be different from whatever storm is expecting
     *
     * Storm expects: public function fetchObject($class_name = "stdClass", array $ctor_args = array())
     */
    public function fetchObject($class_name = null, $ctor_args = null)
    {
        throw new Exception("Object fetching not supported by DB2 wrapper.");
    }

    /**
     * @inheritdoc
     */
    public function getColumnMeta($column)
    {
        throw new Exception("Metadata fetching not supported by DB2 wrapper.");
    }

    /**
     * @inheritdoc
     */
    public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null)
    {
        throw new Exception("Column binding not supported by DB2 wrapper.");
    }

    /**
     * @inheritdoc
     */
    public function debugDumpParams()
    {
        throw new Exception("Debugging dump not supported by DB2 wrapper.");
    }

    // Getters & Setters

    /**
     * Set underlying DB2 statement resource. You should not need to use this externally.
     *
     * @param $statement
     * @return self
     */
    public function setStatement($statement)
    {
        $this->statement = $statement;
        return $this;
    }

    /**
     * Return underlying DB2 statement resource. You should not need to use this externally.
     *
     * @return resource
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * Set underlying DB2 connection resource. You should not need to use this externally.
     *
     * @param $connection
     * @return self
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Return underlying DB2 connection resource. You should not need to use this externally.
     *
     * @return resource
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
