<?php

/**
 * MySQL Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker\Mysql;

use Alaress\Database\Broker\Base;
use Alaress\Database\Broker\IBroker;
use Alaress\Database\Iterator\Mysql\Iterator;
use Alaress\Database\Statement\FunctionCall\Mysql\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Mysql\StoredProcedure;

class Broker extends Base implements IBroker
{
    // These return MySQL specific statement objects

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function)
    {
        return new FunctionCall($this, $function);
    }

    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure)
    {
        return new StoredProcedure($this, $procedure);
    }

    /**
     * @inheritdoc
     */
    public function getIterator($statement, $pdoStatement)
    {
        return new Iterator($this, $statement, $pdoStatement);
    }

    /**
     * @inheritdoc
     */
    public function getDsn() {
        $portString = '';

        if (!empty($this->connector->getPort())) {
            $portString = "port={$this->connector->getPort()};";
        }

        return "mysql:host={$this->connector->getHostname()};{$portString}dbname={$this->connector->getDatabase()}";
    }
}