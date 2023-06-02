<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Alaress\Database\Collector;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\IBuilder;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Traversable;

/**
 * A mock database connector, compatible with Schoolbox\Mapper.
 *
 * To use:
 * <code>
 * $mapper->setDatabase(new MockDatabase($phpunit_test_case));
 * $mapper->getDatabase()->setNextResult('fetch', array(...));
 * </code>
 */
class MockConnector implements IConnector
{
    /**
     * The test case, required for creating mocks
     *
     * @var TestCase
     */
    protected $testCase;

    /**
     * The mock statement
     *
     * @var MockObject|PDOStatement
     */
    protected $mockStatement;

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
        $this->testCase = $test_case;
        $this->resetStatement();
    }

    /**
     * Resets this item's mock statement.
     */
    public function resetStatement()
    {
        $this->mockStatement = $this->testCase->getMockBuilder(PDOStatement::class)->getMock();
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

        $this->mockStatement->expects($this->testCase->any())
                             ->method('fetchAll')
                             ->will($this->testCase->returnValue($this->resultSet));

        $this->mockStatement->expects($this->testCase->any())
                             ->method('fetch')
                             ->will($this->testCase->returnCallback(function () {
                                if ($val = current($this->resultSet)) {
                                    next($this->resultSet);
                                    return $val;
                                }
                                 return null;
                             }));

        $this->mockStatement->expects($this->testCase->any())
                             ->method('execute')
                             ->will($this->testCase->returnValue(true));
    }

    /**
     * Returns the mocked statement.
     *
     * @param string $query Ignored
     * @param array  $args  Ignored
     *
     * @return PDOStatement|MockObject
     */
    public function prepare($query, array $args = [])
    {
        return $this->mockStatement;
    }

//    /**
//     * Mock of quoteIdentifier (used by Schoolbox\Mapper->findByColumnList())
//     *
//     * @param  string $string
//     * @return string
//     */
//    public function quoteIdentifier($string)
//    {
//        return $string;
//    }


    /**
     * Returns the mocked statement.
     *
     * @param string $sql
     * @param array  $args
     * @return PDOStatement
     */
    public function query(string $sql, array $args = null): IStatement
    {
        return $this->prepare($sql, $args);
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
     * This object has itself been set up as an `IQuoter` implementation, so it
     * may just return itself.
     *
     * @return IQuoter
     */
    public function getQuoter(): IQuoter
    {
        return $this;
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
     * @inheritdoc
     */
    public function transact(callable $callback)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function onPersist(callable $callback)
    {
        return $this;
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

    public function connect($hostname, $database, $username, $password, $options = [])
    {
    }

    public function reconnect($options = null)
    {
    }

    public function enableMultipleActiveResultSets()
    {
    }

    public function disableMultipleActiveResultSets()
    {
    }

    public function setTimezone($timezone)
    {
    }

    public function execute($sql)
    {
    }

    public function executeDirectly(string $sql): bool
    {
        return true;
    }

    public function prepareQuery($query, $parameters = [])
    {
    }

    public function executeQuery($sql, $parameters = null)
    {
        // TODO: Implement executeQuery() method.
    }

    public function getQueryIterator($sql, $parameters = null)
    {
        // TODO: Implement getQueryIterator() method.
    }

    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        // TODO: Implement prepareFunctionCall() method.
    }

    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        // TODO: Implement executeFunctionCall() method.
    }

    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        // TODO: Implement prepareStoredProcedure() method.
    }

    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        // TODO: Implement executeStoredProcedure() method.
    }

    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        // TODO: Implement getStoredProcedureIterator() method.
    }

    public function inTransaction()
    {
        // TODO: Implement inTransaction() method.
    }

    public function errorInfo()
    {
        // TODO: Implement errorInfo() method.
    }

    public function errorCode()
    {
        // TODO: Implement errorCode() method.
    }

    public function getHostname()
    {
        // TODO: Implement getHostname() method.
    }

    public function getPort()
    {
        // TODO: Implement getPort() method.
    }

    public function getUsername()
    {
        // TODO: Implement getUsername() method.
    }

    public function getPassword()
    {
        // TODO: Implement getPassword() method.
    }

    public function getDatabase()
    {
        // TODO: Implement getDatabase() method.
    }

    public function getCollector()
    {
        // TODO: Implement getCollector() method.
    }

    public function addPostProcessor($callable)
    {
        // TODO: Implement addPostProcessor() method.
    }

    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        // TODO: Implement prepareInBindings() method.
    }

    public function buildPredicateBuilder($table)
    {
        // TODO: Implement buildPredicateBuilder() method.
    }

    public function getRow(string $sql, array $parameters = null)
    {
        // TODO: Implement getRow() method.
    }

    public function getOne(string $sql, array $parameters = null)
    {
        // TODO: Implement getOne() method.
    }

    public function getAll(string $sql, array $parameters = null): array
    {
        // TODO: Implement getAll() method.
    }

    public function getIterator(string $sql, array $parameters = null): IIterator
    {
        // TODO: Implement getIterator() method.
    }

    public function prepareProcedure(string $name): IStoredProcedure
    {
        // TODO: Implement prepareProcedure() method.
    }

    public function legacyQuote($value): string
    {
        return $value;
    }

    public function trimAll(array $rowset): array
    {
        return $rowset;
    }

    public function trimRow(array $row): array
    {
        return $row;
    }

    public function trimField($fieldValue)
    {
        return $fieldValue;
    }
}
