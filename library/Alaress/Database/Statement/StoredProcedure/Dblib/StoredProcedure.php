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
     * Execute query, and account for output and return vars by extracting them
     * from the last rowset.
     *
     * @param array $values
     * @return bool
     */
    public function execute($values = null)
    {
        $return = parent::execute($values);

        if (!empty($this->parameters['output']) || !empty($this->parameters['return'])) {
            // Finagle output and return out of last rowset
            $rowFromLastRowset = [];

            $hasRowset = true;
            while ($hasRowset) {
                $rowFromLastRowset = $this->statement->fetch(PDO::FETCH_ASSOC);
                $hasRowset         = $this->statement->nextRowset();
            }

            if (!empty($this->parameters['output'])) {
                foreach ($this->parameters['output'] as $name => &$parameter) {
                    $parameter['value'] = $rowFromLastRowset[$name];
                }
                unset($parameter);
            }

            if (!empty($this->parameters['return'])) {
                $this->parameters['return']['value'] = $rowFromLastRowset['return'];
            }
        }

        return $return;
    }

    /**
     * @inheritdoc
     *
     * @throws Exception if return parameter is provided, but it's an unknown type
     */
    public function getSql()
    {
        // Create input/output parameter placeholders

        $declarePlaceholders = [];
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
                $declarePlaceholders[] = $this->buildDeclare($name, $parameter);
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= "@{$name} = :{$name}";
            }
        }

        // Cobble everything together into SQL statement

        $prefix = "EXEC";

        if (isset($this->parameters['return'])) {
            $declarePlaceholders[] = $this->buildDeclare('return', $this->parameters['return']);
            $prefix = "EXEC @return =";
        }

        // Add the function name and its parameter placeholders
        $declarePart = implode('; ', $declarePlaceholders);

        return "{$declarePart} {$prefix} {$this->name} {$parameterPlaceholders}";
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

        // Figure out what type of var this is
        // PDO::PARAM_* vars are NOT bitflags -_- so need to compare values directly
        if (($type & PDO::PARAM_BOOL) === PDO::PARAM_BOOL) {
            $sqlType = 'BIT';

        } elseif (($type & PDO::PARAM_STR) === PDO::PARAM_STR) {
            $sqlType = sprintf('VARCHAR(%d)', $size);

        } elseif (($type & PDO::PARAM_INT) === PDO::PARAM_INT) {
            $sqlType = 'BIGINT';

        } elseif (($type & PDO::PARAM_LOB) === PDO::PARAM_LOB) {
            // GET OUT
            throw new Exception('Invalid return type (PDO::PARAM_LOB)');

        } else {
            // ¯\_(ツ)_/¯
            throw new Exception('Unknown return type');
        }
        return "DECLARE @$name $sqlType = :$name";
    }
}
