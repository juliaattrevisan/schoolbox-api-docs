<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Quoter\Mysql\Quoter;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use BadMethodCallException;
use Closure;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

/**
 * A mock database connector, compatible with Schoolbox\Mapper.
 *
 * To use:
 * <code>
 * $mapper->setDatabase(new MockDatabase($phpunit_test_case));
 * $mapper->getDatabase()->setNextFetch([...]);
 * </code>
 */
class MockConnector implements IConnector
{
    /**
     * The test case, required for creating mocks.
     */
    protected TestCase $testCase;

    /**
     * The mock statement itself.
     */
    protected PDOStatement|MockObject $mockStatement;

    /**
     * The next insert ID to be retrieved.
     */
    protected string|false $nextInsertId;

    /**
     * The next full result set to be retrieved.
     */
    protected array $resultSet;

    /**
     * Builds the mock connection, using the test case to build mock statement.
     *
     * @param TestCase $testCase
     */
    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
        $this->resetStatement();
    }

    /**
     * Resets this item's mock statement.
     * @noinspection PhpUndefinedMethodInspection: `$testCaseGetMockBuilder`
     *               callable is bound to `$this->testCase`, not `$this`
     */
    public function resetStatement(): void
    {
        // getMockBuilder is protected and final: how does that make sense!?
        // Fake it with Closure::bind
        $testCaseGetMockBuilder = Closure::bind(
            (fn($class) => $this->getMockBuilder($class))(...),
            $this->testCase,
            $this->testCase,
        );

        $this->mockStatement = $testCaseGetMockBuilder(PDOStatement::class)->getMock();

        // The query string must be initialized before it is read
        $this->mockStatement->queryString = '';
    }

    /**
     * Sets the next row which would be fetched from the database.
     *
     * @see PDOStatement::fetch
     *
     * @param mixed $result
     */
    public function setNextFetch(mixed $result): void
    {
        $this->setResultSet([$result]);
    }

    /**
     * Sets the next result set which would be fetched from the database.
     *
     * @see PDOStatement::fetchAll
     *
     * @param array $result
     */
    public function setNextFetchAll(array $result): void
    {
        $this->setResultSet($result);
    }

    /**
     * Sets the next insert ID to be fetched.
     *
     * @param string|false $nextInsertId
     */
    public function setNextInsertId(string|false $nextInsertId): void
    {
        $this->nextInsertId = $nextInsertId;
    }

    /**
     * Internal method for setting a result set.
     *
     * @param array $result
     * @noinspection PhpUndefinedMethodInspection: `$testCaseAny` callable is
     *               bound to `$this->testCase`, not `$this`
     */
    private function setResultSet(array $result): void
    {
        $this->resultSet = $result;
        reset($this->resultSet);

        $testCaseAny = Closure::bind(
            (fn() => $this->any())(...),
            $this->testCase,
            $this->testCase,
        );
        $this->mockStatement
            ->expects($testCaseAny())
            ->method('fetchAll')
            ->willReturn($this->resultSet);

        $this->mockStatement
            ->expects($testCaseAny())
            ->method('fetch')
            ->willReturnCallback(
                function () {
                    if ($val = current($this->resultSet)) {
                        next($this->resultSet);
                        return $val;
                    }
                    return null;
                }
            );

        $this->mockStatement
            ->expects($testCaseAny())
            ->method('execute')
            ->willReturn(true);
    }

    /**
     * Helper for raising exceptions for unsupported methods.
     *
     * @param string $methodName
     * @return never-return
     * @throws BadMethodCallException
     */
    private function raiseUnsupportedMethodException(string $methodName): void
    {
        throw new BadMethodCallException(
            sprintf('%s does not support method %s', __CLASS__, $methodName)
        );
    }


    /**
     * Methods defined in base connector, overridden to use mock values
     */

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        return $this->nextInsertId;
    }

    /**
     * Returns the mocked statement.
     *
     * @param string $statement     Ignored
     * @param array  $driverOptions Ignored
     *
     * @return PDOStatement
     */
    public function prepare(string $statement, array $driverOptions = []): PDOStatement
    {
        return $this->mockStatement;
    }

    /**
     * @inheritdoc
     */
    public function getQuoter(): IQuoter
    {
        return new Quoter();
    }


    /**
     * Transaction methods are implemented but do nothing: they are required
     * within other methods
     */

    /**
     * @inheritdoc
     */
    public function transact(callable $callback)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function rollback()
    {
        return true;
    }


    /**
     * No other connector methods are supported
     */

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function query(string $sql, array $parameters = null): IStatement
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function onPersist(callable $callback)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function connect(
        string $hostname,
        string $database,
        string $username,
        #[SensitiveParameter] string $password,
        array $options = [],
    ): bool {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function reconnect($options = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function enableMultipleActiveResultSets()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function disableMultipleActiveResultSets()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function setTimezone($timezone)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function execute($sql)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function executeDirectly(string $sql): bool
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function prepareQuery($query, $parameters = [])
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function executeQuery($sql, $parameters = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getQueryIterator($sql, $parameters = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function inTransaction()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function errorInfo()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function errorCode()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getHostname()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getPort()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getUsername()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getPassword()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getDatabase()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getCollector()
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function addPostProcessor($callable)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function prepareInBindings($externalIds, $prefix = 'param')
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function buildPredicateBuilder($table)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getRow(string $sql, array $parameters = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getOne(string $sql, array $parameters = null)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getAll(string $sql, array $parameters = null): array
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function getIterator(string $sql, array $parameters = null): IIterator
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function prepareProcedure(string $name): IStoredProcedure
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function legacyQuote($value): string
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function trimAll(array $rowset): array
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function trimRow(array $row): array
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function trimField($fieldValue)
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function insert(string $table, array $array): bool
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function update(string $table, array|string $identifier, array $array): bool
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }

    /**
     * Not supported
     *
     * @throws BadMethodCallException
     */
    public function delete(string $table, string $identifier, array|string $value): bool
    {
        $this->raiseUnsupportedMethodException(__FUNCTION__);
    }
}
