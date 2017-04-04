<?php

/**
 * Sqlsrv connector statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Sqlsrv;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;
use Database;
use PHPUnit_Framework_TestCase;

class TestConnector extends PHPUnit_Framework_TestCase
{
    /**
     * Ensure we can establish a connection to Synergetic V68 database on AWS
     */
    public function testConnect()
    {
        // We have to load up a connection using the old database class, otherwise the ConfigMapper is going to fail when
        // it tries to work out if we need to be in debug mode.
        //
        // TODO: Remove this once ConfigMapper has been migrated to these new database classes

        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $factory = new Factory();

        $synergetic67 = $factory->createSqlsrv();
        $result = $synergetic67->connect(
            '172.31.6.236',
            'SynergyOne',
            'sa',
            'Synergetic#1'
        );

        $this->assertTrue($result, "Failed to connect to Synergetic V67 database");

        $synergetic68 = $factory->createSqlsrv();
        $result = $synergetic68->connect(
            '172.31.15.10',
            'Synergetic_AUVIC_ALARESS_PRD',
            'sa',
            'Synergetic#1'
        );

        $this->assertTrue($result, "Failed to connect to Synergetic V68 database");
    }

    /**
     * Test serialization of database connection
     */
    public function testSerialize()
    {
        // We have to load up a connection using the old database class, otherwise the ConfigMapper is going to fail when
        // it tries to work out if we need to be in debug mode.
        //
        // TODO: Remove this once ConfigMapper has been migrated to these new database classes

        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $factory = new Factory();

        $connectorOriginal = $factory->createSqlsrv();
        $connectorOriginal->connect(
            '172.31.15.10',
            'Synergetic_AUVIC_ALARESS_PRD',
            'sa',
            'Synergetic#1'
        );

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