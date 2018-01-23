<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate\SqlFunc\Test;

use Alaress\Database\Predicate\SqlFunc\Coalesce;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use PHPUnit\Framework\TestCase;
use TypeError;
use Alaress\Database\Predicate\InvalidColumnException;

/**
 * Tests that ISqlFunc implementations generate the expected SQL
 */
class TestSqlFunc extends TestCase
{

    /**
     * @throws TypeError If column value is not of correct type
     * @throws InvalidColumnException If unable to parse column name
     */
    public function testCoalesce(): void
    {
        $pb = Assessment\Mapper::getInstance()->getPredicateBuilder();
        $pb->lessThan(new Coalesce(['pp.due_date', 'a.due_date']), 10);
        $this->assertEquals(['COALESCE(`pp`.`due_date`,`a`.`due_date`) < :coalesce_0'], $pb->getWhere());
        $this->assertEquals(['coalesce_0' => 10], $pb->getParams());
    }


    /**
     * @throws TypeError If column value is not of correct type
     * @throws InvalidColumnException If unable to parse column name
     */
    public function testCoalesceDefaultTable(): void
    {
        $pb = Assessment\Mapper::getInstance()->getPredicateBuilder();
        $pb->lessThan(new Coalesce(['pp.due_date', 'due_date']), 10);
        $this->assertEquals(['COALESCE(`pp`.`due_date`,`assessment`.`due_date`) < :coalesce_0'], $pb->getWhere());
        $this->assertEquals(['coalesce_0' => 10], $pb->getParams());
    }
}
