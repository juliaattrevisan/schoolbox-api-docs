<?php

/**
 * IBM DB2 driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Test\TestConnectorTrait;
use PDO;
use PHPUnit_Framework_TestCase;

class TestStatement extends PHPUnit_Framework_TestCase
{
    use TestConnectorTrait;

    /**
     * Test plain old boring statements
     */
    public function testStatement()
    {
        $this->connectDb2Test();

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->db2Test->prepareQuery('SELECT * FROM USERS WHERE NAME_LAST = :nameLast OR NAME_LAST = :nameLast2 ORDER BY NAME_LAST');
        $statement->bindInputs([
            'nameLast' => 'Bailey',
            'nameLast2' => 'Pearce'
        ]);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $this->assertTrue(
                strtolower($row['NAME_LAST']) == 'bailey' ||
                strtolower($row['NAME_LAST']) == 'pearce'
            );
            $count++;
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding (with type and length specified, should get ignored currently)
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->db2Test->prepareQuery('SELECT * FROM USERS WHERE NAME_LAST = :nameLast OR NAME_LAST = :nameLast2 ORDER BY NAME_LAST');
        $statement->bindInputs([
            'nameLast' => [
                'value' => 'Bailey',
                'type' => PDO::PARAM_STR,
                'length' => 50
            ],
            'nameLast2' => [
                'value' => 'Pearce',
                'type' => PDO::PARAM_STR,
                'length' => 50
            ]
        ]);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $this->assertTrue(
                strtolower($row['NAME_LAST']) == 'bailey' ||
                strtolower($row['NAME_LAST']) == 'pearce'
            );
            $count++;
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // executeIterator
        // ----------------------------------------------------------------------------------------------------------------

        $iterator = $this->db2Test->executeQueryIterator(
            'SELECT * FROM USERS WHERE NAME_LAST LIKE :nameLast',
            ['nameLast' => '%a%']
        );

        $count1 = 0;

        foreach ($iterator as $row) {
            $count1++;
            $this->assertTrue(
                strtolower($row['NAME_LAST']) == 'bailey' ||
                strtolower($row['NAME_LAST']) == 'sloane' ||
                strtolower($row['NAME_LAST']) == 'pearce'
            );
        }
        $iterator->rewind();

        $count2 = 0;

        foreach ($iterator as $row) {
            $count2++;
            $this->assertTrue(
                strtolower($row['NAME_LAST']) == 'bailey' ||
                strtolower($row['NAME_LAST']) == 'sloane' ||
                strtolower($row['NAME_LAST']) == 'pearce'
            );
        }

        // Test both iterations returned same number of results

        $this->assertSame($count1, $count2);

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumn
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->db2Test->executeQuery(
            'SELECT NAME_LAST FROM USERS WHERE NAME_LAST = :nameLast',
            ['nameLast' => 'Bailey']
        );

        while ($nameLast = $statement->fetchColumn()) {
            $this->assertSame(strtolower($nameLast), 'bailey');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumnArray
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->db2Test->executeQuery(
            'SELECT * FROM USERS WHERE NAME_LAST = :nameLast',
            ['nameLast' => 'Bailey']
        );

        $statement->execute();

        $arrayNameLast = $statement->fetchColumnArray('NAME_LAST');

        foreach ($arrayNameLast as $nameLast) {
            $this->assertSame(strtolower($nameLast), 'bailey');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchRowArray
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->db2Test->executeQuery(
            'SELECT "ID", "NAME_FIRST", "NAME_LAST" FROM "USERS" ORDER BY "NAME_LAST"',
            ['nameLast' => 'Bailey']
        );

        $statement->execute();

        $users = $statement->fetchRowArray('ID');


        $this->assertSame(strtolower($users[1]['NAME_FIRST']), 'damian');
        $this->assertSame(strtolower($users[1]['NAME_LAST']), 'sloane');

        $this->assertSame(strtolower($users[2]['NAME_FIRST']), 'sam');
        $this->assertSame(strtolower($users[2]['NAME_LAST']), 'bailey');

        $this->assertSame(strtolower($users[3]['NAME_FIRST']), 'aimee');
        $this->assertSame(strtolower($users[3]['NAME_LAST']), 'pearce');
    }
}