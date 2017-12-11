<?php

/**
 * IBM DB2 connector statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;
use Database;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension ibm_db2
 */
class TestConnector extends TestCase
{
    /**
     * Ensure we can establish a connection to DB2 test database
     *
     * @requires extension ibm_db2
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

        $db2Test = $factory->createDb2();
        $result = $db2Test->connect(
            'ec2-54-153-145-255.ap-southeast-2.compute.amazonaws.com:50000',
            'SBOXTEST',
            'sbox',
            'tnp879'
        );

        $this->assertTrue($result, "Failed to connect to DB2 test database");
    }

    /**
     * Test serialization of MySQL database connection
     *
     * @requires extension ibm_db2
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

        $connectorOriginal = $factory->createDb2();
        $connectorOriginal->connect(
            'ec2-54-153-145-255.ap-southeast-2.compute.amazonaws.com:50000',
            'SBOXTEST',
            'sbox',
            'tnp879'
        );

        $connectorSerialized = serialize($connectorOriginal);

        /** @var IConnector $connectorReconstituted */
        $connectorReconstituted = unserialize($connectorSerialized);

        $iterator = $connectorReconstituted->getQueryIterator(
            'SELECT * FROM USERS WHERE NAME_LAST = :nameLast',
            ['nameLast' => 'Bailey']
        );

        foreach ($iterator as $row) {
            $this->assertSame(strtolower($row['NAME_LAST']), 'bailey');
        }
    }
}
