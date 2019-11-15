<?php

/**
 * Mysql connector statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Mysql;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;
use Database;
use PHPUnit\Framework\TestCase;

class TestConnector extends TestCase
{
    /**
     * Ensure we can establish a connection to vagrant Schoolbox database
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

        $schoolbox = $factory->createMysql();
        $result = $schoolbox->connect(
            'localhost',
            'schoolbox',
            'schoolboxvagrant',
            'abc123'
        );

        $this->assertTrue($result, "Failed to connect to Schoolbox database");
    }

    /**
     * Test serialization of MySQL database connection
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

        $connectorOriginal = $factory->createMysql();
        $connectorOriginal->connect(
            'localhost',
            'schoolbox',
            'schoolboxvagrant',
            'abc123'
        );

        $connectorSerialized = serialize($connectorOriginal);

        /** @var IConnector $connectorReconstituted */
        $connectorReconstituted = unserialize($connectorSerialized);

        $iterator = $connectorReconstituted->getQueryIterator(
            'select * from user where lastname = :lastname',
            ['lastname' => 'rhodes']
        );

        foreach ($iterator as $row) {
            $this->assertSame(strtolower($row['lastname']), 'rhodes');
        }
    }
}
