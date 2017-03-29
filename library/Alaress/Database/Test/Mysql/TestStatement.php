<?php

/**
 * Mysql driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mysql;

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
        $this->connectSchoolbox();

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->schoolbox->prepareQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname');
        $statement->bindInputs(['lastname' => 'rhodes']);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['lastname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding (with type and length specified)
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->schoolbox->prepareQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname');
        $statement->bindInputs([
            'lastname' => [
                'value' => 'rhodes',
                'type' => PDO::PARAM_STR,
                'length' => 50
            ]
        ]);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['lastname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // fetch
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->schoolbox->executeQuery('SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
            ['lastname' => 'rhodes']
        );

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['lastname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // executeIterator
        // ----------------------------------------------------------------------------------------------------------------

        $iterator = $this->schoolbox->executeQueryIterator(
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

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumn
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->schoolbox->executeQuery(
            'SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
            ['lastname' => 'Rhodes']
        );

        $statement->execute();

        while ($lastName = $statement->fetchColumn()) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumnArray
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->schoolbox->executeQuery(
            'SELECT `lastname` FROM `user` WHERE `lastname` = :lastname',
            ['lastname' => 'Rhodes']
        );

        $statement->execute();

        $arrayLastName = $statement->fetchColumnArray('lastname');

        foreach ($arrayLastName as $lastName) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchRowArray
        // ----------------------------------------------------------------------------------------------------------------

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

        // ----------------------------------------------------------------------------------------------------------------
        // Test function call
        // ----------------------------------------------------------------------------------------------------------------

        $function = $this->schoolbox->executeFunctionCall('testFunction', [
            'input' => 'Got Milk?'
        ]);

        $this->assertSame($function->fetchColumn(), 'testFunctionDidThis:Got Milk?');
    }
}