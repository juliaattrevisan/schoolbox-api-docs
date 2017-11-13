<?php

/**
 * Base MSSQL predicate builder tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mssql;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestPredicateBuilder extends TestCase
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
     * Skip if SQLSrv driver is not available
     */
    public function setUp()
    {
        $this->skipIfDriverNotAvailable();
        $this->connect();
    }

    /**
     * Test predicate builder quoting
     */
    public function testPredicateBuilder()
    {
        $predicateBuilder = new PredicateBuilder($this->mssqlTest, 'Community');
        $predicateBuilder->equals('Surname', 'rhodes');

        $this->assertSame('[Community].[Surname] = :Surname_0', $predicateBuilder->getWhere()[0]);
        $this->assertSame('rhodes', $predicateBuilder->getParams()['Surname_0']);
    }
}
