<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Psr\Log\LoggerInterface;
use function count;
use InvalidArgumentException;
use function sprintf;
use function strtoupper;
use UnexpectedValueException;

/**
 * Responsible for fetching data from canvas into staging database
 */
class SchemaImporter
{

    /** @var IConnector */
    private $canvasDb;

    /** @var LoggerInterface */
    private $log;

    /** @var string */
    private $canvasDbName;

    /**
     * @param IConnector $canvasDb
     * @param LoggerInterface $log
     * @param string $canvasDbName
     * @throws InvalidArgumentException
     */
    public function __construct(IConnector $canvasDb, LoggerInterface $log, string $canvasDbName)
    {
        $errors = [];
        if (count($errors)) {
            throw new InvalidArgumentException(implode(';', $errors));
        }

        $this->canvasDb = $canvasDb;
        $this->log = $log;
        $this->canvasDbName = $canvasDbName;
    }

    /**
     * Import schema from schema json file.
     * @param string $schemaFile
     */
    public function importFromFile(string $schemaFile): void
    {
        $errors = false;
        $schemaContainer = json_decode(file_get_contents($schemaFile), true);
        $schema = $schemaContainer['schema'];

        foreach ($schema as $tableName => $tableData) {
            $columns = $tableData['columns'];
            $description = str_replace('"', '\"', $tableData['description'] ?? '');

            $indexes = [];
            $columnsSqlArray = [];
            $type = null;
            foreach ($columns as $column) {
                switch ($column['type']) {
                    case 'bigint':
                        $type = 'BIGINT';
                        break;
                    case 'boolean':
                        $type = 'BOOLEAN';
                        break;
                    case 'date':
                        $type = 'DATE';
                        break;
                    case 'datetime':
                        $type = 'DATETIME';
                        break;
                    case 'double precision':
                        $type = 'DOUBLE';
                        break;
                    case 'enum':
                        unset($matches);
                        if (preg_match("/[^']*('[a-zA-Z_]+'(?:,\s*'[a-zA-Z_]+')*)/", $column['description'], $matches)) {
                            $type = "ENUM ({$matches[1]})";
                        } else {
                            $type = "VARCHAR(128)";
                            $this->log->warning(sprintf('Unable to parse enums from "%s", falling back to %s', $column['description'], $type));
                        }
                        break;
                    case 'guid':
                        $type = 'CHAR(36)';
                        break;
                    case 'int':
                    case 'integer':
                        $type = 'INT';
                        break;
                    case 'text':
                        $type = 'TEXT';
                        break;
                    case 'timestamp':
                        $type = 'TIMESTAMP NULL';  // suppress automatic timestamp initialisation and updating https://dev.mysql.com/doc/refman/5.5/en/timestamp-initialization.html
                        break;
                    case 'varchar':
                        $type = "VARCHAR({$column['length']})";
                        break;
                    default:
                        $type = strtoupper($column['type']);
                        $this->log->warning(sprintf("Unrecognised data type: '%s', attempting to treat as '%s'", $column['type'], $type));
                }
                $comment = str_replace('"', '\"', $column['description'] ?? '');
                $columnsSqlArray[] = "`{$column['name']}` $type COMMENT \"{$comment}\"";

                // What do we want to put indexes on?
                switch ($column['type']) {
                    case 'bigint':
                    case 'boolean':
                    case 'date':
                    case 'datetime':
                    case 'double precision':
                    case 'enum':
                    case 'guid':
                    case 'int':
                    case 'integer':
                    case 'timestamp':
                        $indexes[] = $column['name'];
                        break;
                    case 'text':
                    case 'varchar':
                    default:
                        // no index for these
                }
            }

            $columnSql = implode(",\n", $columnsSqlArray);
            $createSql = "CREATE TABLE IF NOT EXISTS `{$this->canvasDbName}`.`{$tableName}` (\n$columnSql\n) COMMENT \"{$description}\";";

            try {
                $this->canvasDb->execute($createSql);

                foreach ($indexes as $columnName) {
                    $indexName = "idx_{$tableName}_{$columnName}";
                    // If index is too long, shorten it to something that very likely will be sufficiently unique
                    if (strlen($indexName) > 64) {
                        $indexName = 'idx_' . md5($tableName) . '_' . substr(md5($columnName), 0, 27); // 4 + 32 + 1 + 27 = 64
                    }
                    $this->canvasDb->execute("CREATE INDEX {$indexName} ON `{$this->canvasDbName}`.`{$tableName}`(`{$columnName}`)");
                }
            } catch (Exception $e) {
                $errors = true;
                $this->log->error($e->getMessage());
            }
        }

        if ($errors) {
            throw new UnexpectedValueException("Errors were encountered parsing json schema file");
        }
    }
}
