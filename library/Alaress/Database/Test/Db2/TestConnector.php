<?php

/**
 * IBM DB2 connector statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('ibm_db2')]
class TestConnector extends TestCase
{
    /**
     * Skip DB2 tests for now.
     */
    public function setUp(): void
    {
        $this->markTestSkipped('Skipping DB2 tests, as test database is inaccessible');
    }

    /**
     * Ensure we can establish a connection to DB2 test database
     */
    #[RequiresPhpExtension('ibm_db2')]
    public function testConnect()
    {
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
     */
    #[RequiresPhpExtension('ibm_db2')]
    public function testSerialize()
    {
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
