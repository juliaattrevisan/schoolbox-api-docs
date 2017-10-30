<?php

/**
 * Dblib driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Mssql\Dblib;

use Alaress\Database\Test\Mssql\AbstractTestStatement;

class TestStatement extends AbstractTestStatement
{
    use SetupTestTrait;

    /**
     * Test iteration of a stored procedure with output parameters and a return value.
     *
     * This doesn't work under the DBLib driver, but does under the SqlSrv driver.
     */
    public function testStoredProcedureIteratorWithOutputParameters()
    {
        // This fails under DBLib
        $this->expectException(\DomainException::class);
        parent::testStoredProcedureIteratorWithOutputParameters();
    }
}
