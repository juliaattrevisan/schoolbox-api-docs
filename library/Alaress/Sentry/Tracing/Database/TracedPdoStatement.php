<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Database;

use Alaress\Sentry\Tracing\Tracer;
use Iterator;
use PDO;
use PDOException;
use PDOStatement;

/**
 * A wrapper around a `PDOStatement`, which wraps `execute` in a call to a
 * span within a trace.
 */
class TracedPdoStatement extends PDOStatement
{
    /**
     * Constructor
     *
     * @param PDOStatement $statement  The statement to execute.
     * @param string       $driverName The name of the driver: used in the span.
     * @param Tracer       $tracer     The tracer to send the span to.
     */
    public function __construct(
        private PDOStatement $statement,
        private string $driverName,
        private Tracer $tracer,
    ) {
        $this->queryString = $statement->queryString;
    }

    /**
     * Execute the wrapped statement, wrapping it in a span.
     *
     * @param array|null $params
     * @return bool
     * @throws PDOException if the execution of the statement raises an error.
     * @noinspection PhpDocMissingThrowsInspection this will only throw PDOException
     */
    public function execute(?array $params = null): bool
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->tracer->runWithinSpan(
            fn () => $this->statement->execute($params),
            $this->statement->queryString,
            'db.sql.query',
            'auto.db',
            [
                'db.system' => $this->driverName,
            ],
        );
    }

    /** All methods below this point are simply wrappers around `$this->statement`. */

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function bindParam(int|string $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function bindColumn(int|string $column, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->statement->bindValue($param, $value, $type);
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        return $this->statement->fetchAll($mode, ...$args);
    }

    public function fetchObject(?string $class = "stdClass", array $constructorArgs = []): object|false
    {
        return $this->statement->fetchObject($class, $constructorArgs);
    }

    public function errorCode(): ?string
    {
        return $this->statement->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    public function setFetchMode($mode, $className = null, ...$params)
    {
        return $this->statement->setFetchMode($mode, $className, ...$params);
    }

    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    public function debugDumpParams(): ?bool
    {
        return $this->statement->debugDumpParams();
    }

    public function getIterator(): Iterator
    {
        return $this->statement->getIterator();
    }
}
