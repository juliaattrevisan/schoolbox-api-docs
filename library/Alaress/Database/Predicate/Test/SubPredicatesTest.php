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
use Alaress\Database\Predicate\InvalidColumnException;
use TypeError;

/**
 * Tests that the Predicate Builder's sub-predicate public methods generate the expected SQL
 */
class SubPredicatesTest extends TestCase
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


    public function testBuildSubBuilder(): void
    {
        $pb    = new TestableBuilder($this->mySqlQuoter, 'table');
        $subPb = $pb->emptyClone();
        $this->assertInstanceOf(TestableBuilder::class, $subPb);
        $this->assertSame($pb->getQuoter(), $subPb->getQuoter());
        $this->assertSame($pb->getTable(), $subPb->getTable());
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     */
    public function testAll()
    {
        $pb    = new Builder($this->mySqlQuoter, 'table');
        $subPb = $pb->emptyClone()
                    ->equals('column', 1)
                    ->equals('column', 2);
        $pb->all($subPb);
        $this->assertEquals('(`table`.`column` = :column_0 AND `table`.`column` = :column_1)', $pb->getWhere()[0]);
        $this->assertEquals($pb->getParams(), ['column_0' => 1, 'column_1' => 2]);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     */
    public function testAny()
    {
        $pb    = new Builder($this->mySqlQuoter, 'table');
        $subPb = $pb->emptyClone()
                    ->equals('column', 1)
                    ->equals('column', 2);
        $pb->any($subPb);
        $this->assertEquals('(`table`.`column` = :column_0 OR `table`.`column` = :column_1)', $pb->getWhere()[0]);
        $this->assertEquals($pb->getParams(), ['column_0' => 1, 'column_1' => 2]);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     */
    public function testNotAll()
    {
        $pb    = new Builder($this->mySqlQuoter, 'table');
        $subPb = $pb->emptyClone()
                    ->equals('column', 1)
                    ->equals('column', 2);
        $pb->notAll($subPb);
        $this->assertEquals('NOT (`table`.`column` = :column_0 AND `table`.`column` = :column_1)', $pb->getWhere()[0]);
        $this->assertEquals($pb->getParams(), ['column_0' => 1, 'column_1' => 2]);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     */
    public function testNotAny()
    {
        $pb    = new Builder($this->mySqlQuoter, 'table');
        $subPb = $pb->emptyClone()
                    ->equals('column', 1)
                    ->equals('column', 2);
        $pb->notAny($subPb);
        $this->assertEquals('NOT (`table`.`column` = :column_0 OR `table`.`column` = :column_1)', $pb->getWhere()[0]);
        $this->assertEquals($pb->getParams(), ['column_0' => 1, 'column_1' => 2]);
    }


    /**
     * @throws InvalidColumnException
     * @throws TypeError
     * @throws \PHPUnit\Framework\Exception
     */
    public function testCollisions()
    {
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan('height', 5);
        $pb->lessThan('height', 50);
        $subPb = $pb->emptyClone()
                    ->equals('height', 10)
                    ->equals('weight', 20);
        $pb->all($subPb);
        $pb->lessThan('height', 60);

        // We added 5 scalar operands
        $this->assertCount(5, $pb->getParams());

        // Each should be present in the WHERE clause
        $where = implode(' AND ', $pb->getWhere());
        foreach (array_keys($pb->getParams()) as $placeholder) {
            $this->assertStringContainsString($placeholder, $where);
        }
    }
}
