<?php

/**
 * Mysql driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Mysql;

use Alaress\Database\Test\TestConnectorTrait;
use PDO;
use PHPUnit\Framework\TestCase;

class TestStatement extends TestCase
{
    use TestConnectorTrait;

    /**
     * Connect to database, begin transaction
     */
    public function setUp()
    {
        $this->connectSchoolbox();
    }

    /**
     * Test fetch with concise preparation and binding
     */
    public function testStatementWithConciseBinding()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->prepareQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname');
            $statement->bindInputs(['lastname' => 'rhodes']);
            $statement->execute();

            $count = 0;

            while ($row = $statement->fetch()) {
                $count++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
            }

            $this->assertGreaterThan(1, $count);
        });
    }

    /**
     * Test fetch with long-winded preparation and binding (with type and length specified)
     *
     */
    public function testStatementWithVerboseBinding()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->prepareQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname');
            $statement->bindInputs([
                'lastname' => [
                    'value'  => 'rhodes',
                    'type'   => PDO::PARAM_STR,
                    'length' => 50,
                ],
            ]);
            $statement->execute();

            $count = 0;

            while ($row = $statement->fetch()) {
                $count++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
            }

            $this->assertGreaterThan(1, $count);
        });
    }

    /**
     * Test fetch with named parameters
     */
    public function testStatementWithNamedParameters()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->executeQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
                ['lastname' => 'rhodes']
            );

            $count = 0;

            while ($row = $statement->fetch()) {
                $count++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
            }

            $this->assertGreaterThan(1, $count);
        });
    }

    /**
     * Test fetch with positional parameters
     */
    public function testStatementWithPositionalParameters()
    {
        $this->schoolbox->transact(function () {
            // Test a second positional parameter, to ensure they're positioned appropriately
            $statement = $this->schoolbox->executeQuery(
                'SELECT `firstname`, `lastname` FROM `user` WHERE `lastname` = ? AND `firstname` = ?',
                ['rhodes', 'rachel']
            );

            $count = 0;

            while ($row = $statement->fetch()) {
                $count++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
                $this->assertSame(strtolower($row['firstname']), 'rachel');
            }

            $this->assertEquals(1, $count);
        });
    }

    /**
     * Test iterator
     */
    public function testStatementIterator()
    {
        $this->schoolbox->transact(function () {
            $iterator = $this->schoolbox->getQueryIterator(
                'SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
                ['lastname' => 'Rhodes']
            );

            $count1 = 0;
            foreach ($iterator as $row) {
                $count1++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
            }
            $iterator->rewind();

            $count2 = 0;
            foreach ($iterator as $row) {
                $count2++;
                $this->assertSame(strtolower($row['lastname']), 'rhodes');
            }

            // Test both iterations returned same number of results

            $this->assertSame($count1, $count2);
        });
    }

    /**
     * Test fetch column
     */
    public function testStatementFetchColumn()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->executeQuery(
                'SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
                ['lastname' => 'Rhodes']
            );

            $statement->execute();

            while ($lastName = $statement->fetchColumn()) {
                $this->assertSame(strtolower($lastName), 'rhodes');
            }
        });
    }

    /**
     * Test fetch of column as array
     */
    public function testStatementFetchColumnArray()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->executeQuery(
                'SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
                ['lastname' => 'Rhodes']
            );

            $statement->execute();

            $arrayLastName = $statement->fetchColumnArray('lastname');

            foreach ($arrayLastName as $lastName) {
                $this->assertSame(strtolower($lastName), 'rhodes');
            }
        });
    }

    /**
     * Test fetch of row as array
     */
    public function testStatementFetchRowArray()
    {
        $this->schoolbox->transact(function () {
            $statement = $this->schoolbox->executeQuery(
                'SELECT `id`, `firstname`, `lastname` FROM `user` WHERE `lastname` = :lastname',
                ['lastname' => 'Rhodes']
            );

            $statement->execute();

            $users = $statement->fetchRowArray('id');

            $this->assertSame(strtolower($users[74]['firstname']), 'peter');
            $this->assertSame(strtolower($users[74]['lastname']), 'rhodes');

            $this->assertSame(strtolower($users[75]['firstname']), 'jo');
            $this->assertSame(strtolower($users[75]['lastname']), 'rhodes');

            $this->assertSame(strtolower($users[206]['firstname']), 'maddison');
            $this->assertSame(strtolower($users[206]['lastname']), 'rhodes');

            $this->assertSame(strtolower($users[229]['firstname']), 'rachel');
            $this->assertSame(strtolower($users[229]['lastname']), 'rhodes');

            $this->assertSame(strtolower($users[230]['firstname']), 'david');
            $this->assertSame(strtolower($users[230]['lastname']), 'rhodes');

            $this->assertSame(strtolower($users[245]['firstname']), 'caleb');
            $this->assertSame(strtolower($users[245]['lastname']), 'rhodes');
        });
    }

    /**
     * Test function call result
     *
     */
    public function testFunctionCall()
    {
        $this->schoolbox->transact(function () {
            $function = $this->schoolbox->executeFunctionCall('REVERSE', [
                'input' => '12345',
            ]);

            $this->assertSame($function->fetchColumn(), '54321');
        });
    }
}
