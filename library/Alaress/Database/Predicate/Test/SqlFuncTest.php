<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Predicate\Test;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\SqlFunc\Coalesce;
use Alaress\Database\Quoter\Mysql\Quoter;
use PHPUnit\Framework\TestCase;
use TypeError;
use Alaress\Database\Predicate\InvalidColumnException;

/**
 * Tests that ISqlFunc implementations generate the expected SQL
 */
class SqlFuncTest extends TestCase
{
    /**
     * Predicate builder being tested
     */
    private Builder $pb;

    /**
     * Set up predicate builder being tested
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->pb = new Builder(new Quoter(), 'assessment');
    }

    /**
     * @throws TypeError If column value is not of correct type
     * @throws InvalidColumnException If unable to parse column name
     */
    public function testCoalesce(): void
    {
        $this->pb->lessThan(new Coalesce(['pp.due_date', 'a.due_date']), 10);
        $this->assertEquals(['COALESCE(`pp`.`due_date`,`a`.`due_date`) < :coalesce_0'], $this->pb->getWhere());
        $this->assertEquals(['coalesce_0' => 10], $this->pb->getParams());
    }


    /**
     * @throws TypeError If column value is not of correct type
     * @throws InvalidColumnException If unable to parse column name
     */
    public function testCoalesceDefaultTable(): void
    {
        $this->pb->lessThan(new Coalesce(['pp.due_date', 'due_date']), 10);
        $this->assertEquals(['COALESCE(`pp`.`due_date`,`assessment`.`due_date`) < :coalesce_0'], $this->pb->getWhere());
        $this->assertEquals(['coalesce_0' => 10], $this->pb->getParams());
    }
}
