<?php

/**
 * IBM DB2 Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker\Db2;

use Alaress\Database\Broker\Base;
use Alaress\Database\Broker\IBroker;
use Alaress\Database\Iterator\Db2\Iterator;
use Alaress\Database\Statement\FunctionCall\Db2\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Db2\StoredProcedure;

class Broker extends Base implements IBroker
{
    // These return DB2 specific statement objects

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
            $portString = "PORT={$this->connector->getPort()};";
        }

        return "\"ibm:DRIVER={IBM DB2 ODBC DRIVER};HOSTNAME={$this->connector->getHostname()};{$portString}DATABASE={$this->connector->getDatabase()};PROTOCOL=TCPIP;";
    }
}