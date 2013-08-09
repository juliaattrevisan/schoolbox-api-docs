<?php
/**
 * A mock database, compatible with Schoolbox\Mapper.
 *
 * To use:
 * <code>
 * $mapper->setDatabase(new MockDatabase($phpunit_test_case));
 * $mapper->getDatabase()->setNextResult('fetch', array(...));
 * </code>
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Test;

use PHPUnit_Framework_TestCase;

class MockDatabase {

    /**
     * The test case, required for creating mocks
     * @var \PHPUnit_Framework_TestCase
     */
    protected $test_case;

    /**
     * The mock statement
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mock_statement;

    /**
     * Builds the mock connection, using the test case to build mock statement.
     *
     * @param PHPUnit_Framework_TestCase $test_case
     */
    public function __construct(PHPUnit_Framework_TestCase $test_case) {
        $this->test_case = $test_case;
        $this->resetStatement();
    }

    /**
     * Resets this item's mock statement.
     */
    public function resetStatement() {
        $this->mock_statement = $this->test_case->getMock('\\PDOStatement');
    }

    /**
     * Quick method for setting expected result from database statement.
     *
     * @param string $method The method to set result on (fetch, fetchAll, etc.)
     * @param mixed  $result
     */
    public function setNextResult($method, $result) {
        $this->mock_statement->expects($this->test_case->any())
            ->method($method)
            ->will($this->test_case->returnValue($result));
    }

    /**
     * Calls $this->setNextResult('fetch', $result)
     *
     * @param mixed $result
     */
    public function setNextFetch($result) {
        $this->setNextResult('fetch', $result);
    }

    /**
     * Calls $this->setNextResult('fetchAll', $result)
     *
     * @param mixed $result
     */
    public function setNextFetchAll($result) {
        $this->setNextResult('fetchAll', $result);
    }

    /**
     * Returns the mocked statement.
     *
     * @param string $query Ignored
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function prepare($query) {
        return $this->mock_statement;
    }
}