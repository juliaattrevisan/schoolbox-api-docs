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
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    /**
     * Ensure we can establish a connection to vagrant Schoolbox database
     */
    public function testConnect()
    {
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
