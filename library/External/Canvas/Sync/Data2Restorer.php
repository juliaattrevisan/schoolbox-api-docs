<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Exception;
use External\Canvas\Importer\CanvasDb;
use Psr\Log\LoggerInterface;

/**
 * Responsible for fetching data from canvas into staging database
 */
class Data2Restorer extends DataRestorer
{
    public function __construct(
        CanvasDb $canvasDb,
        private LoggerInterface $log,
        bool $persistChanges,
        private array $tableNames,
        array $schema,
        private Data2Connector $data2Connector,
    ) {
        parent::__construct($canvasDb, $log, $persistChanges, $tableNames, $schema);
    }

    /**
     * Creates schema from schema json file.
     * @return void
     * @throws \Schoolbox\External\Exception
     */
    public function restoreSchema(): void
    {
        $this->dropCanvasDbTables();
        foreach ($this->tableNames as $tableName) {
            $tableSchema     = $this->data2Connector->fetchTableSchema($tableName);
            $columnsSqlArray = [];
            $indexes         = [];
            foreach (['key', 'value', 'meta'] as $propertyType) {
                foreach ($tableSchema['properties'][$propertyType]['properties'] as $propColumnName => $propColumn) {
                    if ($propColumnName === 'action') {
                        continue;
                    }
                    // if columns is object, store all the properties as separate columns
                    if (($propColumn['type'] ?? '') === 'object') {
                        $columns = $propColumn['properties'];
                    } else {
                        $columns = [$propColumnName => $propColumn];
                    }
                    foreach ($columns as $columnName => $column) {
                        $columnType        = $column['type'] ?? $column['oneOf'][0]['type'] ?? null;
                        $type              = $this->getColumnMySqlDataType(
                            $columnType,
                            isset($column['maxLength']) ? (int)($column['maxLength']) : null,
                            isset($column['format']) ? (string)($column['format']) : null,
                        );
                        $comment           = str_replace('"', '\"', $column['description'] ?? '');
                        $columnsSqlArray[] = "`$columnName` $type COMMENT \"$comment\"";
                        if ($this->requiresIndex($columnType)) {
                            $indexes[] = $columnName;
                        }
                    }
                }
            }
            $columnSql = implode(",\n", $columnsSqlArray);
            $comment   = str_replace('"', '\"', $tableSchema['properties']['value']['title']);
            $createSql = "CREATE TABLE IF NOT EXISTS `{$this->canvasDb->getCanvasDbName()}`.`$tableName` (\n$columnSql\n) COMMENT \"$comment\";";
            $this->log->debug("Creating table `{$this->canvasDb->getCanvasDbName()}`.`$tableName`");

            try {
                $this->executeIfPersisting($createSql);
                foreach ($indexes as $columnName) {
                    $this->addIndex($tableName, $columnName);
                }
            } catch (Exception $e) {
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
                $mySqlType = 'VARCHAR(512)';
                break;
            case 'boolean':
                // The dump data comes in as either 'true' or 'false'.
                $mySqlType = 'VARCHAR(8)';
                break;
            case 'number':
                $mySqlType = 'DOUBLE';
                break;
            case 'integer':
                $mySqlType = match ($format) {
                    'int64' => 'BIGINT',
                    // includes 'int32'
                    default => 'INT',
                };
                break;
            case 'string':
                $mySqlType = match ($format) {
                    'date-time' => 'VARCHAR(32)',
                    default => empty($columnLength) ? 'LONGTEXT' : sprintf('VARCHAR(%d)', $columnLength * 2),
                };
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
            case 'array':
                // no index for these
                return false;
            default:
        }
        $this->log->warning("Unrecognised column type: '$columnType'");
        return false;
    }

    /**
     * @inheritDoc
     */
    public function addExtraIndexes(): void
    {
        // noop
    }
}
