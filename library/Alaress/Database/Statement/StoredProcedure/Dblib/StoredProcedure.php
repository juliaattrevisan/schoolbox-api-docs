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
                    // varchar(length)
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
}
