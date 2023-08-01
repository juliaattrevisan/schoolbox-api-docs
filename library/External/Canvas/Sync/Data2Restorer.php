<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Psr\Log\LoggerInterface;

/**
 * Responsible for fetching data from canvas into staging database
 */
class Data2Restorer extends DataRestorer
{
    public function __construct(
        IConnector $canvasDb,
        private LoggerInterface $log,
        private string $canvasDbName,
        bool $persistChanges,
        private array $tableNames,
        array $schema,
        private Data2Connector $data2Connector,
    ) {
        parent::__construct($canvasDb, $log, $canvasDbName, $persistChanges, $tableNames, $schema);
    }

    /**
     * Creates schema from schema json file.
     * @return void
     * @throws \Schoolbox\External\Exception
     * @see
     */
    public function restoreSchema(): void
    {
        $this->dropCanvasDbTables();
        foreach ($this->tableNames as $tableName) {
            $tableSchema     = $this->data2Connector->fetchTableSchema($tableName);
            $columnsSqlArray = [];
            $indexes         = [];
            foreach ($tableSchema['properties']['value']['properties'] as $columnName => $column) {
                $columnType = $column['type'] ?? $column['oneOf'][0]['type'] ?? null;
                $type = $this->getColumnMySqlDataType(
                    $columnType,
                    isset($column['maxLength']) ? (int)($column['maxLength']) : null,
                    isset($column['format']) ? (string)($column['format']) : null,
                );
                $comment           = str_replace('"', '\"', $column['description'] ?? '');
                $columnsSqlArray[] = "`$columnName` $type COMMENT \"{$comment}\"";
                if ($this->requiresIndex($columnType)) {
                    $indexes[] = $columnName;
                }
            }
            $columnSql = implode(",\n", $columnsSqlArray);
            $comment = str_replace('"', '\"', $tableSchema['properties']['value']['title']);
            $createSql = "CREATE TABLE IF NOT EXISTS `{$this->canvasDbName}`.`{$tableName}` (\n$columnSql\n) COMMENT \"$comment\";";
            $this->log->debug("Creating table `{$this->canvasDbName}`.`{$tableName}`");

            try {
                $this->executeIfPersisting($createSql);
                foreach ($indexes as $columnName) {
                    $this->addIndex($tableName, $columnName);
                }
            } catch (Exception $e) {
                $errors = true;
                $this->log->error($e->getMessage());
            }
        }
    }

    /**
     * Convert a logical data type to a concrete MySQL data type
     *
     * @param string      $columnType
     * @param null|int    $columnLength
     * @param string|null $format
     * @return string
     */
    private function getColumnMySqlDataType(string $columnType, ?int $columnLength, ?string $format): string
    {
        // object - assignment_groups.rules
        switch ($columnType) {
            case 'object':
            case 'array':
                $mySqlType = 'VARCHAR(64)';
                break;
            case 'boolean':
                // The dump data comes in as either 'true' or 'false'.
                $mySqlType = 'VARCHAR(8)';
                break;
            case 'number':
                $mySqlType = match ($format) {
                    'float64' => 'DECIMAL',
                    default => 'DOUBLE',
                };
                break;
            case 'integer':
                $mySqlType = match ($format) {
                    'int32' => 'INT(32)',
                    'int64' => 'INT(64)',
                    default => 'INT',
                };
                break;
            case 'string':
                switch ($format) {
                    case 'date-time':
                        $mySqlType = 'DATETIME';
                        break;
                    default:
                        // There found a case where storing 330 chars when length is 256. Doubling it for now.
                        $length    = $columnLength * 2;
                        $mySqlType = "VARCHAR({$length})";
                        break;
                }
                break;
            default:
                $mySqlType = strtoupper($columnType);
                $this->log->warning(sprintf("Unrecognised data type: '%s', attempting to treat as '%s'", $columnType, $mySqlType));
                return $mySqlType;
        }
        return $mySqlType;
    }


    /**
     * Returns whether we should index a column of the specified data type
     *
     * @param string $columnType
     * @return bool
     */
    private function requiresIndex(string $columnType): bool
    {
        switch ($columnType) {
            case 'boolean':
            case 'integer':
            case 'number':
                // Index these
                return true;
            case 'string':
            case 'object':
                // no index for these
                return false;
            default:
        }
        $this->log->warning("Unrecognised column type: '$columnType'");
        return false;
    }
}
