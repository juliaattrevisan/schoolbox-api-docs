<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Sqlsrv;

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
        $this->connectSynergetic68();

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->prepareQuery('SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname');
        $statement->bindInputs(['Surname' => 'rhodes']);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // fetch with long-winded preparation and binding (with type and length specified)
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->prepareQuery('SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname');
        $statement->bindInputs([
            'Surname' => [
                'value' => 'rhodes',
                'type' => PDO::PARAM_STR,
                'length' => 50
            ]
        ]);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // fetch
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );
        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);

        // ----------------------------------------------------------------------------------------------------------------
        // executeIterator
        // ----------------------------------------------------------------------------------------------------------------

        $iterator = $this->synergetic68->executeQueryIterator(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $count1 = 0;
        foreach ($iterator as $row) {
            $count1++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $count2 = 0;
        foreach ($iterator as $row) {
            $count2++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        // Test both iterations returned same number of results

        $this->assertSame($count1, $count2);

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumn
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $statement->execute();

        while ($lastName = $statement->fetchColumn()) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchColumnArray
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $statement->execute();

        $arrayLastName = $statement->fetchColumnArray('Surname');

        foreach ($arrayLastName as $lastName) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }

        // ----------------------------------------------------------------------------------------------------------------
        // fetchRowArray
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->synergetic68->executeQuery(
            'SELECT [ID], [Given1], [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $statement->execute();

        $users = $statement->fetchRowArray('ID');

        $this->assertSame(strtolower($users[131]['Given1']), 'peter');
        $this->assertSame(strtolower($users[131]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[132]['Given1']), 'joanne');
        $this->assertSame(strtolower($users[132]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[134]['Given1']), 'maddison');
        $this->assertSame(strtolower($users[134]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[133]['Given1']), 'rachel');
        $this->assertSame(strtolower($users[133]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[135]['Given1']), 'david');
        $this->assertSame(strtolower($users[135]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[493]['Given1']), 'caleb');
        $this->assertSame(strtolower($users[493]['Surname']), 'rhodes');

        $this->assertSame(strtolower($users[498]['Given1']), 'megan');
        $this->assertSame(strtolower($users[498]['Surname']), 'rhodes');
    }

    /**
     * Test stored procedures
     */
    public function testStoredProcedure()
    {
        // ----------------------------------------------------------------------------------------------------------------
        // Execute a stored procedure with boring old input values
        // ----------------------------------------------------------------------------------------------------------------

        $this->connectSynergetic68();

        $statement = $this->synergetic68->executeStoredProcedure('spsStudentPersonal', [
            'ID' => ['value' => 133],
            'FileYear' => ['value' => 2010]
        ]);

        $student = $statement->fetch();

        $this->assertSame(strtolower($student['Surname']), 'rhodes');
        $this->assertSame(strtolower($student['Given1']), 'rachel');

        // ----------------------------------------------------------------------------------------------------------------
        // Execute a stored procedure with output parameters and a return value
        // ----------------------------------------------------------------------------------------------------------------

        $currentDay = null;
        $calendarDate = null;
        $calendarDay = null;
        $message = null;
        $definitionDay = null;
        $result = null;

        $returns = ['value' => &$result, 'type' => PDO::PARAM_INT, 'length' => 4];

        $outputs = [
            'CurrentDay' => ['value' => &$currentDay, 'type' => PDO::PARAM_INT, 'length' => 4],
            'CalendarDate' => ['value' => &$calendarDate, 'type' => PDO::PARAM_STR, 'length' => 255],
            'CalendarDay' => ['value' => &$calendarDay, 'type' => PDO::PARAM_INT, 'length' => 4],
            'Message' => ['value' => &$message, 'type' => PDO::PARAM_STR, 'length' => 255],
            'DefinitionDay' => ['value' => &$definitionDay, 'type' => PDO::PARAM_INT, 'length' => 4]
        ];

        $statement = $this->synergetic68->executeStoredProcedure('spgTimetableDay',
            [
                'TimetableGroup' => 'SEN',
                'Date' => '2010-10-03'
            ],
            $outputs,
            $returns
        );

        $statement->nextRowset();

        $this->assertSame($currentDay, 0);
        $this->assertSame($calendarDate, 'Dec 30 1899 12:00AM');
        $this->assertSame($calendarDay, 0);
        $this->assertSame($message, 'No classes on this day');
        $this->assertSame($definitionDay, null);
        $this->assertSame($result, 1);

        // ----------------------------------------------------------------------------------------------------------------
        // Test iterating multiple recordsets returned by stored procedure
        // ----------------------------------------------------------------------------------------------------------------

        $iterator = $this->synergetic68->executeStoredProcedureIterator('uTestMultipleRecordsets');

        $count = 0;
        $expectedResults = ['1', '2', '3', '4'];

        foreach ($iterator as $row) {
            $this->assertSame($expectedResults[$count++], $row['test']);
        }

        $iterator->nextRowset();

        $expectedResults = ['a', 'b', 'c', 'd'];

        $count = 0;
        foreach ($iterator as $row) {
            $this->assertSame($expectedResults[$count++], $row['test']);
        }
    }
}