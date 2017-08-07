<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mssql\Dblib;

use Alaress\Database\Broker\Dblib\Broker as DblibBroker;
use Alaress\Database\Test\TestConnectorTrait;

/**
 * Setup trait for DBLib tests
 */
trait SetupTestTrait
{
    use TestConnectorTrait;

    /**
     * Skip if DBLib driver is not available
     */
    public function skipIfDriverNotAvailable()
    {
        if (!DblibBroker::isAvailable()) {
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
        return $this->connectDblibTest();
    }
}
