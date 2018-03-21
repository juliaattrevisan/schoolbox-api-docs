<?php
/**
 * MSSQL Query
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement\Query\Dblib;

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
                        $statement->bindParam($name, $input['value'], PDO::PARAM_LOB);
                    } else {
                        $statement->bindValue($name, $input['value'], $input['type']);
                    }
                } else {
                    $statement->bindValue($name, $input['value'] );
                }
            }
        }
    }

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
}
