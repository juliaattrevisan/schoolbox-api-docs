<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use Alaress\Database;
use PDO;
use Psr\Log\LoggerInterface;
use function count;
use function sprintf;
use function strtoupper;
use UnexpectedValueException;

/**
 * Responsible for fetching data from canvas into staging database
 */
class DataRestorer
{

    /** @var IConnector */
    private $canvasDb;

    /** @var LoggerInterface */
    private $log;

    /** @var string */
    private $canvasDbName;

    /** @var bool */
    private $persistChanges;


    /**
     * @param IConnector      $canvasDb
     * @param LoggerInterface $log
     * @param string          $canvasDbName
     * @param bool            $persistChanges If false, don't persist changes to staging database
     */
    public function __construct(IConnector $canvasDb, LoggerInterface $log, string $canvasDbName, bool $persistChanges)
    {
        $this->canvasDb = $canvasDb;
        $this->log = $log;
        $this->canvasDbName = $canvasDbName;
        $this->persistChanges = $persistChanges;
    }


    /**
     * Creates schema from schema json file.
     *
     * @param array $schema
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    public function restoreSchema(array $schema): void
    {
        $this->dropCanvasDbTables();

        $errors = false;

        foreach ($schema as $tableIndex => $tableData) {
            $columns = $tableData['columns'];
            $tableName = $tableData['tableName'];
            $description = str_replace('"', '\"', $tableData['description'] ?? '');

            // https://dev.mysql.com/doc/workbench/en/wb-migration-database-postgresql-typemapping.html
            $indexes = [];
            $columnsSqlArray = [];
            $type = null;
            foreach ($columns as $column) {
                switch ($column['type']) {
                    case 'bigint':
                        $type = 'BIGINT';
                        break;
                    case 'boolean':
                        // The dump data comes in as either 'true' or 'false'.
                        $type = 'VARCHAR(8)';
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
                        // It's not possible to get all enum values without the dump data.
                        $type = 'VARCHAR(128)';
                        break;
                    case 'guid':
                        $type = 'CHAR(36)';
                        break;
                    case 'int':
                    case 'integer':
                        $type = 'INT';
                        break;
                    case 'text':
                        $type = 'LONGTEXT';
                        break;
                    case 'timestamp':
                        $type = 'DATETIME';
                        // $type = 'TIMESTAMP NULL';  // suppress automatic timestamp initialisation and updating https://dev.mysql.com/doc/refman/5.5/en/timestamp-initialization.html
                        break;
                    case 'varchar':
                        // There found a case where storing 330 chars when length is 256. Doubling it for now.
                        $length = (int)$column['length'] * 2;
                        $type = "VARCHAR({$length})";
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
                $this->executeIfPersisting($createSql);

                foreach ($indexes as $columnName) {
                    $indexName = "idx_{$tableName}_{$columnName}";
                    // If index is too long, shorten it to something that very likely will be sufficiently unique
                    if (strlen($indexName) > 64) {
                        $indexName = 'idx_' . md5($tableName) . '_' . substr(md5($columnName), 0, 27); // 4 + 32 + 1 + 27 = 64
                    }
                    $this->executeIfPersisting("CREATE INDEX {$indexName} ON `{$this->canvasDbName}`.`{$tableName}`(`{$columnName}`)");
                }
            } catch (Database\Exception $e) {
                $errors = true;
                $this->log->error($e->getMessage());
            }
        }

        if ($errors) {
            throw new UnexpectedValueException("Errors were encountered parsing json schema file");
        }
    }


    /**
     * Stores data from the data file in the data dir to tables
     *
     * @param $tableName
     * @param $tableFile
     * @throws Database\Exception
     */
    public function restoreTableData($tableName, $tableFile): void
    {
        $sql = "TRUNCATE {$this->canvasDbName}.{$tableName}";
        $this->executeIfPersisting($sql);

        $sql = "LOAD DATA INFILE '$tableFile' INTO TABLE {$this->canvasDbName}.{$tableName}";
        try {
            $this->executeIfPersisting($sql);
        } catch (Database\Exception $e) {
            $this->log->warning("Loading data failed for $tableFile: " . $e->getMessage());
        }
        $warnings = $this->persistChanges ? $this->canvasDb->executeQuery('show warnings')->fetchAll() : [];
        if (count($warnings) > 0) {
            $this->log->warning("MySQL warning occurred while loading $tableFile");
            foreach ($warnings as $warning) {
                $this->log->warning(implode(': ', $warning));
            }
        }
    }


    /**
     * Drops all tables in the Canvas staging database
     *
     * @throws Database\Exception
     */
    private function dropCanvasDbTables(): void
    {
        $this->log->info("Dropping all tables from {$this->canvasDbName}");
        $this->executeIfPersisting('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $this->canvasDb->executeQuery(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = :db_name',
            ['db_name' => $this->canvasDbName]
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $this->log->debug("Dropping table $table");
            $this->executeIfPersisting("DROP TABLE IF EXISTS {$this->canvasDb->quoteIdentifier($table)}");
        }
        $this->executeIfPersisting('SET FOREIGN_KEY_CHECKS = 1');
    }


    /**
     * Optionally execute the specified SQL
     *
     * @param string $sql
     * @throws Database\Exception
     */
    private function executeIfPersisting(string $sql): void
    {
        if ($this->persistChanges) {
            $this->canvasDb->execute($sql);
        }
    }
}
