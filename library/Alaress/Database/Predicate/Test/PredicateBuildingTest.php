<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Predicate\Test;

use Alaress\Database\Predicate\Builder;
use PHPUnit\Framework\TestCase;
use Alaress\Database\Quoter\Mysql;
use Alaress_Date_Format;
use Alaress\Database\Predicate\InvalidColumnException;
use TypeError;
use UnderflowException;

/**
 * Tests that the Predicate Builder's public methods generate the expected SQL
 */
class PredicateBuildingTest extends TestCase
{
    /** @var Mysql\Quoter */
    private $mySqlQuoter;


    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->mySqlQuoter = new Mysql\Quoter();
    }


    /**
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    public function testAddColumnList()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->addColumnList(['column1' => 1, 'column2' => 2]);
        $this->assertEquals(['`table`.`column1` = :column1_0', '`table`.`column2` = :column2_0'], $pb->getWhere());
        $this->assertEquals($pb->getParams(), ['column1_0' => 1, 'column2_0' => 2]);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testIsNull()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isNull('column');
        $this->assertEquals(['`table`.`column` IS NULL'], $pb->getWhere());
        $this->assertEquals($pb->getParams(), []);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testNotNull()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notNull('column');
        $this->assertEquals(['`table`.`column` IS NOT NULL'], $pb->getWhere());
        $this->assertEquals($pb->getParams(), []);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testIsTrue()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isTrue('column');
        $this->assertEquals(['`table`.`column` IS TRUE'], $pb->getWhere());
        $this->assertEquals($pb->getParams(), []);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testIsFalse()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isFalse('column');
        $this->assertEquals(['`table`.`column` IS FALSE'], $pb->getWhere());
        $this->assertEquals($pb->getParams(), []);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testEquals()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals('column', 10);
        $this->assertEquals(['`table`.`column` = :column_0'], $pb->getWhere());
        $this->assertEquals(10, $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws \PHPUnit\Framework\Exception
     */
    public function testEqualsAlaressDateFormat()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals('column', new Alaress_Date_Format('2010-01-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT));
        $this->assertEquals(['`table`.`column` = :column_0'], $pb->getWhere());
        /** @var Alaress_Date_Format $adf */
        $adf = $pb->getParams()['column_0'];
        $this->assertInstanceOf(Alaress_Date_Format::class, $adf);
        $this->assertEquals('2010-01-01T00:00:00+00:00', $adf->__toString());
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testNotEquals()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notEquals('column', 10);
        $this->assertEquals(['`table`.`column` <> :column_0'], $pb->getWhere());
        $this->assertEquals(10, $pb->getParams()['column_0']);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     * @throws UnderflowException
     */
    public function testIn()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in('column', [10, 20]);
        $this->assertEquals(['`table`.`column` IN (:column_0,:column_1)'], $pb->getWhere());
        $this->assertEquals(10, $pb->getParams()['column_0']);
        $this->assertEquals(20, $pb->getParams()['column_1']);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     * @throws UnderflowException
     */
    public function testNotIn()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notIn('column', [10, 20]);
        $this->assertEquals(['`table`.`column` NOT IN (:column_0,:column_1)'], $pb->getWhere());
        $this->assertEquals(10, $pb->getParams()['column_0']);
        $this->assertEquals(20, $pb->getParams()['column_1']);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testLike()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->like('column', '%value%');
        $this->assertEquals(['`table`.`column` LIKE :column_0'], $pb->getWhere());
        $this->assertEquals('%value%', $pb->getParams()['column_0']);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testLikeAny()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->likeAny('column', ['%value1%', '%value2%']);
        $this->assertEquals(['(`table`.`column` LIKE :column_0 OR `table`.`column` LIKE :column_1)'], $pb->getWhere());
        $this->assertEquals('%value1%', $pb->getParams()['column_0']);
        $this->assertEquals('%value2%', $pb->getParams()['column_1']);
    }


    /**
     * @throws InvalidColumnException
     */
    public function testNotLike()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLike('column', '%value%');
        $this->assertEquals(['`table`.`column` NOT LIKE :column_0'], $pb->getWhere());
        $this->assertEquals('%value%', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testLessThan()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThan('column', 'value');
        $this->assertEquals(['`table`.`column` < :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testLessThanOrEqualTo()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThanOrEqualTo('column', 'value');
        $this->assertEquals(['`table`.`column` <= :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testGreaterThan()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan('column', 'value');
        $this->assertEquals(['`table`.`column` > :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testGreaterThanOrEqualTo()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThanOrEqualTo('column', 'value');
        $this->assertEquals(['`table`.`column` >= :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testNotLessThan()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLessThan('column', 'value');
        $this->assertEquals(['`table`.`column` >= :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @throws TypeError
     * @throws InvalidColumnException
     */
    public function testNotGreaterThan()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notGreaterThan('column', 'value');
        $this->assertEquals(['`table`.`column` <= :column_0'], $pb->getWhere());
        $this->assertEquals('value', $pb->getParams()['column_0']);
    }


    /**
     * @param string|int|null $from
     * @param string|int|null $to
     * @param string[]        $where
     * @param mixed[]         $params
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \TypeError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBetween')]
    public function testBetween($from, $to, array $where, array $params)
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->between('column', $from, $to);
        $this->assertEquals($where, $pb->getWhere());
        $this->assertEquals($params, $pb->getParams());
    }


    /**
     * @param string|int|null $from
     * @param string|int|null $to
     * @param string[]        $where
     * @param mixed[]         $params
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBetweenDates')]
    public function testBetweenDates($from, $to, array $where, array $params)
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->betweenDates('column', $from, $to);
        $this->assertEquals($where, $pb->getWhere());
        $this->assertEquals($params, $pb->getParams());
    }


    /**
     * @param string $column
     * @param string $table
     * @param string $quotedColumnAndTable
     * @throws InvalidColumnException
     * @throws TypeError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTableNames')]
    public function testTableNames(string $column, string $table, string $quotedColumnAndTable)
    {
        $pb = new Builder($this->mySqlQuoter, $table);
        $this->assertSame($table, $pb->getTable());

        $pb->equals($column, 'value');
        $whereClause = implode(' AND ', $pb->getWhere());
        $this->assertEquals(0, strpos($whereClause, $quotedColumnAndTable));
    }

    // Data Providers


    /**
     * @return mixed[][]
     */
    public static function providerBetween(): array
    {
        return [
            [null, null, [], []],
            [10, null, ['`table`.`column` >= :column_0'], ['column_0' => 10]],
            [null, 20, ['`table`.`column` <= :column_0'], ['column_0' => 20]],
            [10, 20, ['(`table`.`column` >= :column_0 AND `table`.`column` <= :column_1)'], ['column_0' => 10, 'column_1' => 20]],
        ];
    }


    /**
     * @return mixed[][]
     */
    public static function providerBetweenDates(): array
    {
        $date1    = new Alaress_Date_Format('2010-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $date2    = new Alaress_Date_Format('2010-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT);
        return [
            [null, null, [], []],
            [$date1, null, ['`table`.`column` >= :column_0'], ['column_0' => $date1->getDatabase()]],
            [$date1, null, ['`table`.`column` >= :column_0'], ['column_0' => $date1->getDatabase()]],
            [null, $date2, ['`table`.`column` <= :column_0'], ['column_0' => $date2->getDatabase()]],
            [null, $date2, ['`table`.`column` <= :column_0'], ['column_0' => $date2->getDatabase()]],
            [
                $date1,
                $date2,
                ['(`table`.`column` >= :column_0 AND `table`.`column` <= :column_1)'],
                ['column_0' => $date1->getDatabase(), 'column_1' => $date2->getDatabase()],
            ],
        ];
    }


    /**
     * @return string[][]
     */
    public static function providerTableNames(): array
    {
        return [
            ['column', 'table', '`table`.`column`'],
            ['column', 'camelCase', '`camelCase`.`column`'],
            ['column', 'snake_case', '`snake_case`.`column`'],
            ['column', 'camelCaseCase', '`camelCaseCase`.`column`'],
            ['column', 'snake_case_case', '`snake_case_case`.`column`'],
            ['column', 'mixedCase_case', '`mixedCase_case`.`column`'],
            ['column', 'noQuotingNeeded123$_', '`noQuotingNeeded123$_`.`column`'],
            ['column', 'quotingNeeded!@#$%^&*()_-+=`~', '`quotingNeeded!@#$%^&*()_-+=``~`.`column`'],
        ];
    }
}
