<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('ibm_db2')]
class TestPredicateBuilder extends TestCase
{
    use TestConnectorTrait;

    /**
     * Skip DB2 tests for now.
     */
    public function setUp(): void
    {
        $this->markTestSkipped('Skipping DB2 tests, as test database is inaccessible');
    }

    /**
     * Test predicate builder quoting
     */
    #[RequiresPhpExtension('ibm_db2')]
    public function testPredicateBuilder()
    {
        $this->connectDb2Test();

        $predicateBuilder = new PredicateBuilder($this->db2Test->getQuoter(), 'USERS');
        $predicateBuilder->equals('NAME_LAST', 'rhodes');

        $this->assertSame('"USERS"."NAME_LAST" = :NAME_LAST_0', $predicateBuilder->getWhere()[0]);
        $this->assertSame('rhodes', $predicateBuilder->getParams()['NAME_LAST_0']);
    }
}
