<?php

/**
 * Base test for MSSQL connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mssql;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestConnector extends TestCase
{
    use TestConnectorTrait;

    /**
     * This method should call `$this->markTestSkipped` if the database
     * connector is unavailable.
     */
    abstract public function skipIfDriverNotAvailable();

    /**
     * Connects to MSSQL database given the correct connector.
     *
     * @return bool
     */
    abstract public function connect();

    /**
     * Skip if SQLSrv driver is not available
     */
    public function setUp()
    {
        $this->skipIfDriverNotAvailable();
    }

    /**
     * Ensure we can establish a connection to test MSSQL database on AWS
     */
    public function testConnect()
    {
        $this->assertTrue($this->connect(), "Failed to connect to MSSQL database");
    }

    /**
     * Test serialization of database connection
     */
    public function testSerialize()
    {
        $this->connect();
        $connectorOriginal = $this->mssqlTest;

        $connectorSerialized = serialize($connectorOriginal);

        /** @var IConnector $connectorReconstituted */
        $connectorReconstituted = unserialize($connectorSerialized);

        $iterator = $connectorReconstituted->executeQueryIterator(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        foreach ($iterator as $row) {
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }
    }
}
