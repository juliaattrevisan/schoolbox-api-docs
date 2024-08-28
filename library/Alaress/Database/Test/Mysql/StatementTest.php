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

class StatementTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Connect to database, begin transaction
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        // Test with inserting terms
        $this->schoolbox->executeQuery('UPDATE term SET archived=TRUE');
        $termQuery = $this->schoolbox->prepareQuery('
            INSERT INTO term (id, title, code, start, display_finish, actual_finish, archived)
            VALUES (:id, :title, :code, NOW(), NOW(), NULL, FALSE)
        ');
        $termQuery->execute(['id' => 1000, 'title' => 'Term 1000', 'code' => 'T1000']);
        $termQuery->execute(['id' => 1001, 'title' => 'Term 1001', 'code' => 'T1001']);
    }

    /**
     * Roll back when test finishes
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollBack();
    }

    /**
     * Test fetch with concise preparation and binding
     */
    public function testStatementWithConciseBinding()
    {
        $statement = $this->schoolbox->prepareQuery(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE'
        );
        $statement->bindInputs(['code' => 'T1000']);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame($row['title'], 'Term 1000');
        }

        $this->assertEquals(1, $count);
    }

    /**
     * Test fetch with long-winded preparation and binding (with type and length specified)
     *
     */
    public function testStatementWithVerboseBinding()
    {
        $statement = $this->schoolbox->prepareQuery(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE'
        );
        $statement->bindInputs([
            'code' => [
                'value'  => 'T1000',
                'type'   => PDO::PARAM_STR,
                'length' => 50,
            ],
        ]);
        $statement->execute();

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame($row['title'], 'Term 1000');
        }

        $this->assertEquals(1, $count);
    }

    /**
     * Test fetch with named parameters
     */
    public function testStatementWithNamedParameters()
    {
        $statement = $this->schoolbox->executeQuery(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE',
            ['code' => 'T1000']
        );

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame($row['title'], 'Term 1000');
        }

        $this->assertEquals(1, $count);
    }

    /**
     * Test fetch with positional parameters
     */
    public function testStatementWithPositionalParameters()
    {
        // Test a second positional parameter, to ensure they're positioned appropriately
        $statement = $this->schoolbox->executeQuery(
            'SELECT `title` FROM `term` WHERE `code` = ? AND `id` = ? AND archived = FALSE',
            ['T1000', 1000]
        );

        $count = 0;

        while ($row = $statement->fetch()) {
            $count++;
            $this->assertSame($row['title'], 'Term 1000');
        }

        $this->assertEquals(1, $count);
    }

    /**
     * Test iterator
     */
    public function testStatementIterator()
    {
        $iterator = $this->schoolbox->getQueryIterator(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE',
            ['code' => 'T1000']
        );

        $count1 = 0;
        foreach ($iterator as $row) {
            $count1++;
            $this->assertSame($row['title'], 'Term 1000');
        }
        $iterator->rewind();

        $count2 = 0;
        foreach ($iterator as $row) {
            $count2++;
            $this->assertSame($row['title'], 'Term 1000');
        }

        // Test both iterations returned same number of results

        $this->assertSame($count1, $count2);
    }

    /**
     * Test fetch column
     */
    public function testStatementFetchColumn()
    {
        $statement = $this->schoolbox->executeQuery(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE',
            ['code' => 'T1000']
        );

        $statement->execute();

        while ($title = $statement->fetchColumn()) {
            $this->assertSame($title, 'Term 1000');
        }
    }

    /**
     * Test fetch of column as array
     */
    public function testStatementFetchColumnArray()
    {
        $statement = $this->schoolbox->executeQuery(
            'SELECT `title` FROM `term` WHERE `code` = :code AND archived = FALSE',
            ['code' => 'T1000']
        );

        $statement->execute();

        $arrayTerm = $statement->fetchColumnArray('title');

        foreach ($arrayTerm as $term) {
            $this->assertSame($term, 'Term 1000');
        }
    }

    /**
     * Test fetch of row as array
     */
    public function testStatementFetchRowArray()
    {
        $statement = $this->schoolbox->executeQuery(
            'SELECT `id`, `title`, `code` FROM `term` WHERE `archived` = FALSE'
        );

        $statement->execute();

        $users = $statement->fetchRowArray('id');

        $this->assertSame($users[1000]['title'], 'Term 1000');
        $this->assertSame($users[1000]['code'], 'T1000');

        $this->assertSame($users[1001]['title'], 'Term 1001');
        $this->assertSame($users[1001]['code'], 'T1001');
    }

    /**
     * Test function call result
     *
     */
    public function testFunctionCall()
    {
        $function = $this->schoolbox->executeFunctionCall('REVERSE', [
            'input' => '12345',
        ]);

        $this->assertSame($function->fetchColumn(), '54321');
    }
}
