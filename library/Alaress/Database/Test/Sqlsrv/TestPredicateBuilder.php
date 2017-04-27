<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Sqlsrv;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit_Framework_TestCase;

class TestPredicateBuilder extends PHPUnit_Framework_TestCase
{
    use TestConnectorTrait;

    /**
     * Test predicate builder quoting
     */
    public function testPredicateBuilder()
    {
        $this->connectSynergetic68();

        $predicateBuilder = new PredicateBuilder($this->synergetic68, 'Community');
        $predicateBuilder->equals('Surname', 'rhodes');

        $this->assertSame('[Community].[Surname] = :Surname_0', $predicateBuilder->getWhere()[0]);
        $this->assertSame('rhodes', $predicateBuilder->getParams()['Surname_0']);
    }
}