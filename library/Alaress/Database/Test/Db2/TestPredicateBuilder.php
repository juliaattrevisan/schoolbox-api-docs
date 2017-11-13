<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension ibm_db2
 */
class TestPredicateBuilder extends TestCase
{
    use TestConnectorTrait;

    /**
     * Test predicate builder quoting
     *
     * @requires extension ibm_db2
     */
    public function testPredicateBuilder()
    {
        $this->connectDb2Test();

        $predicateBuilder = new PredicateBuilder($this->db2Test, 'USERS');
        $predicateBuilder->equals('NAME_LAST', 'rhodes');

        $this->assertSame('"USERS"."NAME_LAST" = :NAME_LAST_0', $predicateBuilder->getWhere()[0]);
        $this->assertSame('rhodes', $predicateBuilder->getParams()['NAME_LAST_0']);
    }
}
