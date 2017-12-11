<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\StoredProcedure\Dblib;

use Alaress\Database\Exception;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\StoredProcedure\Base;
use DomainException;
use PDO;

/**
 * Stored procedure class for DBLib databases.
 *
 * DBLib stored procs have a known limitation; they cannot return a usable
 * result set AND return or output vars in the same query. This is due to DBLib
 * not supporting true prepared statements or bound outputs; we work around the
 * limitation by extracting return/output vars from the last rowset of the
 * query, but to do this we need to skip any rowsets from the stored proc.
 */
class StoredProcedure extends Base
{
    /**
     * DBLib demands that the current rowset be fully iterated before moving to
     * the next rowset, otherwise the below error gets thrown:
     *
     * `General error: PDO_DBLIB: dbresults() returned FAIL`
     *
     * Iterate transparently in this implementation, instead of shoehorning in
     * `fetch()` loops whenever we use `nextRowset()`.
     *
     * @return bool
     */
    public function nextRowset()
    {
        while ($this->fetch() !== false) {
            continue;
        }
        return parent::nextRowset();
    }

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
                // Under PHP5.5:
                // Fetching the first row then skipping to next rowset errors.
                // Work around by iterating entire result set.
                foreach ($this->statement as $row) {
                    $rowFromLastRowset = $row;
                }
                $hasRowset = $this->statement->nextRowset();
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
     * Query iteration is unable to populate return or output vars;
     * if this query uses them, then this will raise an exception.
     *
     * @param array $values
     * @return IIterator
     * @throws DomainException if this has output or return parameters
     */
    public function getIterator($values = null)
    {
        if (!empty($this->parameters['output']) || !empty($this->parameters['return'])) {
            throw new DomainException(
                'DBLib cannot retrieve output or return values for iterated procedures; '.
                'Use `execute()` instead, or remove output and return values.'
            );
        }
        return parent::getIterator($values);
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
        $outputSelectPlaceholders = [];
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
                $declarePlaceholders[] = $this->buildDeclare('Output'.$name, $parameter);
                if (!empty($parameterPlaceholders)) {
                    $parameterPlaceholders .= ", ";
                }
                $parameterPlaceholders .= "@{$name} = @Output{$name} OUTPUT";
                $outputSelectPlaceholders[] = $this->buildOutputSelect($name);
            }
        }

        // Cobble everything together into SQL statement

        $prefix = "EXEC";

        if (isset($this->parameters['return'])) {
            $declarePlaceholders[] = $this->buildDeclare('Outputreturn', $this->parameters['return']);
            $outputSelectPlaceholders[] = $this->buildOutputSelect('return');
            $prefix = "EXEC @Outputreturn =";
        }

        // Add SELECT parts
        $outputSelectPart = '';
        if (count($outputSelectPlaceholders)) {
            $outputSelectPart = ";\nSELECT " . implode(', ', $outputSelectPlaceholders);
        }

        // Add the function name and its parameter placeholders
        $declarePart = '';
        if (count($declarePlaceholders)) {
            $declarePart = implode(";\n", $declarePlaceholders).";\n";
        }

        return "{$declarePart}{$prefix} {$this->name} {$parameterPlaceholders} {$outputSelectPart}";
    }

    /**
     * DBLib doesn't support true prepared statements, or output/returns from
     * stored procedures.
     *
     * No binding of output params is done here; "output" params are instead
     * added to a select statement, and retrieved from the final rowset.
     *
     * @param string $name
     * @param &array $param
     */
    protected function internalBindOutputParam($name, &$param)
    {
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
        return "DECLARE @$name $sqlType";
    }

    /**
     * Creates a SELECT part for an output or return parameter.
     *
     * @param string $name
     * @return string
     */
    protected function buildOutputSelect($name)
    {
        return "@Output{$name} AS \"{$name}\"";
    }
}
