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

use Alaress\Database\Quoter\IQuoter;
use Alaress\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use Traversable;

class MockDatabase implements IDatabase, IQuoter
{

    /**
     * The test case, required for creating mocks
     *
     * @var TestCase
     */
    protected $test_case;

    /**
     * The mock statement
     *
     * @var PHPUnit_Framework_MockObject_MockObject|PDOStatement
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
     * @param TestCase $test_case
     */
    public function __construct(TestCase $test_case)
    {
        $this->test_case = $test_case;
        $this->resetStatement();
    }

    /**
     * Resets this item's mock statement.
     */
    public function resetStatement()
    {
        $this->mock_statement = $this->test_case->getMockBuilder(PDOStatement::class)->getMock();
    }

    /**
     * Calls $this->setNextResult('fetch', $result)
     *
     * @param mixed $result
     */
    public function setNextFetch($result)
    {
        $this->setResultSet([$result]);
    }

    /**
     * Calls $this->setNextResult('fetchAll', $result)
     *
     * @param mixed $result
     */
    public function setNextFetchAll($result)
    {
        $this->setResultSet($result);
    }


    /** @var array|Traversable|null */
    protected $resultSet;


    /**
     * @param array|Traversable|null $result
     */
    protected function setResultSet($result)
    {
        $this->resultSet = $result;
        reset($this->resultSet);

        $this->mock_statement->expects($this->test_case->any())
                             ->method('fetchAll')
                             ->will($this->test_case->returnValue($this->resultSet));

        $this->mock_statement->expects($this->test_case->any())
                             ->method('fetch')
                             ->will($this->test_case->returnCallback(function () {
                                 if ($val = current($this->resultSet)) {
                                     next($this->resultSet);
                                     return $val;
                                 }
                                 return null;
                             }));

        $this->mock_statement->expects($this->test_case->any())
                             ->method('execute')
                             ->will($this->test_case->returnValue(true));
    }

    /**
     * Returns the mocked statement.
     *
     * @param string $query Ignored
     * @param array  $args  Ignored
     *
     * @return PDOStatement|PHPUnit_Framework_MockObject_MockObject
     */
    public function prepare($query, array $args = [])
    {
        return $this->mock_statement;
    }

    /**
     * Mock of quoteIdentifier (used by Schoolbox\Mapper->findByColumnList())
     *
     * @param  string $string
     * @return string
     */
    public function quoteIdentifier($string)
    {
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
     * @inheritdoc
     */
    public function getEscapedOperand(string $defaultTable, string $columnName): string
    {
        return $columnName;
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
