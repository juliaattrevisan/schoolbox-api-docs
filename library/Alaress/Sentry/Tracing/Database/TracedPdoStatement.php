<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Database;

use Alaress\Sentry\Tracing\Tracer;
use Iterator;
use JetBrains\PhpStorm\ArrayShape;
use PDO;
use PDOStatement;

/**
 * Class WrappedPdoStatement
 */
class TracedPdoStatement extends PDOStatement
{
    public string $queryString;

    public function __construct(private PDOStatement $statement, private string $driverName)
    {
        $this->queryString = $statement->queryString;
    }

    public function execute(?array $params = null): bool
    {
        return Tracer::getDefaultTrace()->runWithinSpan(
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

    #[ArrayShape([0 => "string", 1 => "int", 2 => "string"])] public function errorInfo(): array
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

    #[ArrayShape([
        "name"          => "string",
        "len"           => "int",
        "precision"     => "int",
        "oci:decl_type" => "int|string",
        "native_type"   => "string",
        "scale"         => "int",
        "flags"         => "array",
        "pdo_type"      => "int",
    ])] public function getColumnMeta(int $column): array|false
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
