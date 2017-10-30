<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mysql;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;

class TestPredicateBuilder extends TestCase
{
    use TestConnectorTrait;

    /**
     * Test predicate builder quoting
     */
    public function testPredicateBuilder()
    {
        $this->connectSchoolbox();

        $predicateBuilder = new PredicateBuilder($this->schoolbox, 'user');
        $predicateBuilder->equals('lastname', 'rhodes');

        $this->assertSame('`user`.`lastname` = :lastname_0', $predicateBuilder->getWhere()[0]);
        $this->assertSame('rhodes', $predicateBuilder->getParams()['lastname_0']);
    }
}
