<?php

/**
 * MSSQL Query
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Statement\Query\Sqlsrv;

use Alaress\Database\Statement\Query\Base;
use PDOStatement;
use PDO;

class Query extends Base
{
    /**
     * This modifies the parent method by adding the required PDO::SQLSRV_ENCODING_BINARY parameter when binding LOB objects
     * otherwise its identical
     *
     * @inheritdoc
     */
    protected function bindStatement()
    {
        /** @var PDOStatement $statement */
        $statement = $this->statement;

        if (isset($this->parameters['input'])) {
            foreach ($this->parameters['input'] as $name => $input) {
                if (isset($input['type'])) {
                    if ($input['type'] == PDO::PARAM_LOB) {
                        $statement->bindParam($name, $input['value'], PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
                    } else {
                        $statement->bindValue($name, $input['value'], $input['type']);
                    }
                } else {
                    $statement->bindValue($name, $input['value']);
                }
            }
        }
    }
}
