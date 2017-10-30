<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mssql;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Test\TestConnectorTrait;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestStatement extends TestCase
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
     * * Skip if SQLSrv driver is not available
     * * Connect to database, begin transaction
     */
    public function setUp()
    {
        $this->skipIfDriverNotAvailable();
        $this->connect();
        if ($this->mssqlTest instanceof IConnector) {
            $this->mssqlTest->beginTransaction();
        }
    }

    /**
     * Roll back when done
     */
    public function tearDown()
    {
        if ($this->mssqlTest instanceof IConnector) {
            $this->mssqlTest->rollBack();
        }
    }

    /**
     * Test with long-winded preparation and binding
     */
    public function testStatementWithPreparationAndBinding()
    {
        $statement = $this->mssqlTest->prepareQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname'
        );
        $statement->bindInputs(['Surname' => 'rhodes']);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);
    }

    /**
     * Test running the same statement as a prepare, then as an execute.
     */
    public function testPrepareThenExecuteStatement()
    {
        // prepare once
        $statement = $this->mssqlTest->prepareQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname'
        );
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


        // Because of some object reference voodoo which I can't figure out;
        // running the statement again fails under DBLib
        // (presumably because the object reference isn't cleaned up)
        // All I can do for now is to unset the statement object.
        unset($statement);


        // and now fetch
        $statement = $this->mssqlTest->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );
        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);
    }

    /**
     * Test with long-winded preparation and binding (with type and length specified)
     */
    public function testStatementWithPreparationAndFullBinding()
    {
        $statement = $this->mssqlTest->prepareQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname'
        );
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
    }

    /**
     * Test fetch() method
     */
    public function testFetch()
    {
        $statement = $this->mssqlTest->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );
        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        $this->assertGreaterThan(1, $count);
    }

    /**
     * Test executeIterator() method
     */
    public function testExecuteIterator()
    {
        $iterator = $this->mssqlTest->executeQueryIterator(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $count1 = 0;
        foreach ($iterator as $row) {
            $count1++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        // Have disabled rewinding due to performance issues with cursors being enabled, so this test will fail

        /*
        $count2 = 0;
        foreach ($iterator as $row) {
            $count2++;
            $this->assertSame(strtolower($row['Surname']), 'rhodes');
        }

        // Test both iterations returned same number of results

        $this->assertSame($count1, $count2);
        */
    }

    /**
     * Test fetchColumn() method
     */
    public function testFetchColumn()
    {
        $statement = $this->mssqlTest->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $statement->execute();

        while ($lastName = $statement->fetchColumn()) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }
    }

    /**
     * Test columnArray() method
     */
    public function testColumnArray()
    {
        $statement = $this->mssqlTest->executeQuery(
            'SELECT [Surname] FROM [dbo].[Community] WHERE [Surname] = :Surname',
            ['Surname' => 'rhodes']
        );

        $statement->execute();

        $arrayLastName = $statement->fetchColumnArray('Surname');

        foreach ($arrayLastName as $lastName) {
            $this->assertSame(strtolower($lastName), 'rhodes');
        }
    }

    /**
     * Test rowArray() method
     */
    public function testRowArray()
    {
        $statement = $this->mssqlTest->executeQuery(
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
     * Test a stored procedure with boring old input values.
     */
    public function testStoredProcedure()
    {
        $statement = $this->mssqlTest->executeStoredProcedure('spsStudentPersonal', [
            'ID' => ['value' => 133, 'type' => PDO::PARAM_INT],
            'FileYear' => ['value' => 2010, 'type' => PDO::PARAM_INT]
        ]);

        $student = $statement->fetch();

        $this->assertSame(strtolower($student['Surname']), 'rhodes');
        $this->assertSame(strtolower($student['Given1']), 'rachel');
    }

    /**
     * Test a stored procedure with output parameters and a return value.
     */
    public function testStoredProcedureWithOutputParameters()
    {
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

        $statement = $this->mssqlTest->executeStoredProcedure(
            'spgTimetableDay',
            [
                'TimetableGroup' => 'SEN',
                'Date' => '2010-10-03'
            ],
            $outputs,
            $returns
        );

        $statement->nextRowset();

        $this->assertEquals($currentDay, 0);
        $this->assertEquals($calendarDate, 'Dec 30 1899 12:00AM');
        $this->assertEquals($calendarDay, 0);
        $this->assertEquals($message, 'No classes on this day');
        $this->assertNull($definitionDay, null);
        $this->assertEquals($result, 1);
    }

    /**
     * Test iteration of a stored procedure with output parameters and a return value.
     */
    public function testStoredProcedureIteratorWithOutputParameters()
    {
        $result   = null;
        $returns  = ['value' => &$result, 'type' => PDO::PARAM_INT, 'length' => 4];
        $outputs  = [];
        $iterator = $this->mssqlTest->executeStoredProcedureIterator(
            'uReturnValueTest',
            [],
            $outputs,
            $returns
        );

        // Verify rows
        $rows = [];
        foreach ($iterator as $row) {
            $rows[] = $row;
        }
        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['id']);
        $this->assertEquals('Alaress', $rows[0]['value']);

        // Result is not applied until next rowset
        $iterator->nextRowset();
        $this->assertEquals(-5, $result);
    }

    /**
     * Test iterating multiple recordsets returned by stored procedure
     */
    public function testStoredProcedureWithMultipleRecordSets()
    {
        $iterator = $this->mssqlTest->executeStoredProcedureIterator('uTestMultipleRecordsets');

        $count = 0;
        $expectedResults = ['1', '2', '3', '4'];

        foreach ($iterator as $row) {
            $this->assertEquals($expectedResults[$count++], $row['test']);
        }

        $iterator->nextRowset();

        $expectedResults = ['a', 'b', 'c', 'd'];

        $count = 0;
        foreach ($iterator as $row) {
            $this->assertEquals($expectedResults[$count++], $row['test']);
        }
    }

    /**
     * Test function call stored procedure
     */
    public function testStoredProcedureFunctionCall()
    {
        $function = $this->mssqlTest->executeFunctionCall('dbo.fnTimeStr', [
            'ADateTime' => '2016-01-05 13:47:27'
        ]);

        $this->assertSame($function->fetchColumn(), "1:47pm");
    }
}
