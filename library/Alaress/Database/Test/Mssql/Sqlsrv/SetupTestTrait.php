<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * Skip if DBLib driver is not available
     */
    public function skipIfDriverNotAvailable()
    {
        if (!SqlsrvBroker::isAvailable()) {
            $this->markTestSkipped();
        }
    }

    /**
     * Connect using DBLib
     *
     * @return bool
     */
    public function connect()
    {
        return $this->connectSqlsrvTest();
    }
}
