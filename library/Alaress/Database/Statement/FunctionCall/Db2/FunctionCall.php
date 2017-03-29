<?php

/**
 * IBM DB2 Function call
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\FunctionCall\Db2;

use Alaress\Database\Statement\FunctionCall\Base;

class FunctionCall extends Base
{
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