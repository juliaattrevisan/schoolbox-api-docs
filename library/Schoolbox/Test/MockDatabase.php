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

use Alaress\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit_Framework_TestCase;

class MockDatabase implements IDatabase
{

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
     * The next insert ID to be used.
     *
     * @var mixed
     */
    protected $nextInsertId;

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
     * @param array  $args  Ignored
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function prepare($query, array $args = []) {
        return $this->mock_statement;
    }

    /**
     * Mock of quoteIdentifier (used by Schoolbox\Mapper->findByColumnList())
     *
     * @param  string $string
     * @return string
     */
    public function quoteIdentifier($string) {
        return $string;
    }


    /**
     * Returns the mocked statement.
     *
     * @param string $statement
     * @param array  $args
     * @return PDOStatement
     */
    public function query($statement, array $args = null)
    {
        return $this->prepare($statement, $args);
    }


    /**
     * Mock of quote
     *
     * @param string $string
     * @param int    $parameter_type
     * @return string
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        return $string;
    }


    /**
     * Mock of escapeForLike
     *
     * @param  string $string
     * @return string
     */
    public function escapeForLike($string)
    {
        return $string;
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function commit()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function rollback()
    {
        return true;
    }

    /**
     * @param  null $name
     * @return int
     */
    public function lastInsertId($name = null)
    {
        return $this->nextInsertId;
    }

    /**
     * @param int $nextInsertId
     */
    public function setNextInsertId($nextInsertId)
    {
        $this->nextInsertId = $nextInsertId;
    }
}
