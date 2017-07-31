<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure\Dblib;

use Alaress\Database\Exception;
use Alaress\Database\Statement\StoredProcedure\Base;
use PDO;

/**
 * Stored procedure class for DBLib databases.
 */
class StoredProcedure extends Base
{
    /**
     * @inheritdoc
     *
     * @throws Exception if return parameter is provided, but it's an unknown type
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

        // Output or return parameters cause a completely different problem
        if (isset($this->parameters['output'])) {
            foreach ($this->parameters['output'] as $name => $parameter) {
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= "@{$name} = :{$name}";
            }
        }

        // Cobble everything together into SQL statement

        $prefix = "EXEC";

        if (isset($this->parameters['return'])) {
            // Figure out what type of var to declare
            $type = $this->parameters['return']['type'];
            $size = $this->parameters['return']['length'];
            switch ($type) {
                case PDO::PARAM_BOOL:
                    $sqlType = 'BIT';
                    break;

                case PDO::PARAM_STR:
                    $sqlType = sprintf('VARCHAR(%d)', $size);
                    break;

                case PDO::PARAM_INT:
                    $sqlType = 'BIGINT';
                    break;

                case PDO::PARAM_LOB:
                    // GET OUT
                    throw new Exception('Invalid return type (PDO::PARAM_LOB)');

                default:
                    // ¯\_(ツ)_/¯
                    throw new Exception('Unknown return type');
            }
            $prefix = "DECLARE @return $sqlType = :return; EXEC @return =";
        }

        // Add the function name and its parameter placeholders

        return "{$prefix} {$this->name} {$parameterPlaceholders}";
    }

    /**
     * DBLib doesn't support true prepared statements, or output/returns from
     * stored procedures, so bind as normal parameters and wrangle in the query.
     *
     * @param string $name
     * @param &array $param
     */
    protected function internalBindOutputParam($name, &$param)
    {
        $this->statement->bindValue($name, $param['value'], $param['type']);
    }

    /**
     * Creates a DECLARE statement for an output or return parameter.
     *
     * @param string $name
     * @param array  $param
     * @return string
     * @throws Exception if param's type isn't PARAM_BOOL, PARAM_STR or PARAM_INT
     */
    protected function buildDeclare($name, $param)
    {
        $type = $param['type'];
        $size = $param['length'];
        switch ($type) {
            case PDO::PARAM_BOOL:
                $sqlType = 'BIT';
                break;

            case PDO::PARAM_STR:
                $sqlType = sprintf('VARCHAR(%d)', $size);
                break;

            case PDO::PARAM_INT:
                $sqlType = 'BIGINT';
                break;

            case PDO::PARAM_LOB:
                // GET OUT
                throw new Exception('Invalid return type (PDO::PARAM_LOB)');

            default:
                // ¯\_(ツ)_/¯
                throw new Exception('Unknown return type');
        }
        return "DECLARE @$name $sqlType = :$name;";
    }
}
