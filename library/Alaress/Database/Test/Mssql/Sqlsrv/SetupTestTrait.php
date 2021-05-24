<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Mssql\Sqlsrv;

use Alaress\Database\Broker\Sqlsrv\Broker as SqlsrvBroker;
use Alaress\Database\Test\TestConnectorTrait;

/**
 * Setup trait for SQLSrv tests
 */
trait SetupTestTrait
{
    use TestConnectorTrait;

    /**
     * Skip if SQLSrv driver is not available
     */
    public function skipIfDriverNotAvailable()
    {
        if (!SqlsrvBroker::isAvailable()) {
            $this->markTestSkipped();
        }
    }

    /**
     * Connect using SQLSrv
     *
     * @return bool
     */
    public function connect()
    {
        return $this->connectSqlsrvTest();
    }
}
