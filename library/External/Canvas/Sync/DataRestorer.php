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
use UnexpectedValueException;
use function count;
use function implode;
use function md5;
use function sprintf;
use function str_replace;
use function strlen;
use function strtoupper;
use function substr;

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

        $this->log->info("Re-creating DB schema...");
        $errors = false;
        foreach ($schema as $tableIndex => $tableData) {
            $columns     = $tableData['columns'];
            $tableName   = $tableData['tableName'];
            $description = str_replace('"', '\"', $tableData['description'] ?? '');

            // https://dev.mysql.com/doc/workbench/en/wb-migration-database-postgresql-typemapping.html
            $columnsSqlArray = [];
            $type            = null;
            foreach ($columns as $column) {
                $type              = $this->getColumnMySqlDataType($column['type'], $column['length']);
                $comment           = str_replace('"', '\"', $column['description'] ?? '');
                $columnsSqlArray[] = "`{$column['name']}` $type COMMENT \"{$comment}\"";
            }

            $columnSql = implode(",\n", $columnsSqlArray);
            $createSql = "CREATE TABLE IF NOT EXISTS `{$this->canvasDbName}`.`{$tableName}` (\n$columnSql\n) COMMENT \"{$description}\";";
            $this->log->debug("Creating table `{$this->canvasDbName}`.`{$tableName}`");

            try {
                $this->executeIfPersisting($createSql);
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
     * Adds indexes to all columns of specific data types
     *
     * @param array $schema
     * @throws UnexpectedValueException
     */
    public function addStandardIndexes(array $schema): void
    {
        $this->log->info("Adding standard indexes...");
        $errors = false;
        foreach ($schema as $tableIndex => $tableData) {
            $tableName = $tableData['tableName'];
            $indexes   = [];
            foreach ($tableData['columns'] as $column) {
                // What do we want to put indexes on?
                if ($this->requiresIndex($column['type'])) {
                    $indexes[] = $column['name'];
                }
            }

            foreach ($indexes as $columnName) {
                try {
                    $this->addIndex($tableName, $columnName);
                } catch (Database\Exception $e) {
                    $errors = true;
                    $this->log->error($e->getMessage());
                }
            }
        }
        if ($errors) {
            throw new UnexpectedValueException("Errors were encountered parsing json schema file");
        }
    }


    /**
     * Adds extra indexes to table columns
     *
     * We know from experience that we need indexes on some columns that are not indexed by default.
     * This is essentially a whitelist of known cases that require indexes, or the sync tool is too slow for practical use.
     *
     * @throws Database\Exception
     */
    public function addExtraIndexes(): void
    {
        $extra = [
             'course_section_dim' => [
                 'sis_source_id',
             ],
        ];
        foreach($extra as $tableName => $columnNames){
            foreach($columnNames as $columnName) {
                $this->addIndex($tableName, $columnName);
            }
        }
    }


    /**
     * Convert a logical data type to a concrete MySQL data type
     *
     * @param string $columnType
     * @param        $columnLength    // TODO what data type is this?
     * @return string
     */
    private function getColumnMySqlDataType(string $columnType, $columnLength): string
    {
        switch ($columnType) {
            case 'bigint':
                $mySqlType = 'BIGINT';
                break;
            case 'boolean':
                // The dump data comes in as either 'true' or 'false'.
                $mySqlType = 'VARCHAR(8)';
                break;
            case 'date':
                $mySqlType = 'DATE';
                break;
            case 'datetime':
                $mySqlType = 'DATETIME';
                break;
            case 'double precision':
                $mySqlType = 'DOUBLE';
                break;
            case 'enum':
                // It's not possible to get all enum values without the dump data.
                $mySqlType = 'VARCHAR(128)';
                break;
            case 'guid':
                $mySqlType = 'CHAR(36)';
                break;
            case 'int':
            case 'integer':
                $mySqlType = 'INT';
                break;
            case 'text':
                $mySqlType = 'LONGTEXT';
                break;
            case 'timestamp':
                $mySqlType = 'DATETIME';
                // $type = 'TIMESTAMP NULL';  // suppress automatic timestamp initialisation and updating https://dev.mysql.com/doc/refman/5.5/en/timestamp-initialization.html
                break;
            case 'varchar':
                // There found a case where storing 330 chars when length is 256. Doubling it for now.
                $length = (int)$columnLength * 2;
                $mySqlType = "VARCHAR({$length})";
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
                // Index these
                return true;
            case 'text':
            case 'varchar':
                // no index for these
                return false;
            default:
        }
        $this->log->warning("Unrecognised column type: '$columnType'");
        return false;
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
        $countTables = count($tables);
        $this->log->debug(sprintf("%d tables found", $countTables));
        foreach ($tables as $idx => $table) {
            $idx++;
            $this->log->debug("Dropping table $idx/$countTables '$table'");
            $this->executeIfPersisting("DROP TABLE IF EXISTS {$this->canvasDb->quoteIdentifier($table)}");
        }
        $this->executeIfPersisting('SET FOREIGN_KEY_CHECKS = 1');
    }


    /**
     * Adds a database index so our subsequent SQL statements can join on the columns efficiently
     *
     * @param string $tableName
     * @param string $columnName
     * @throws Database\Exception
     */
    private function addIndex(string $tableName, string $columnName): void
    {
        $indexName = "idx_{$tableName}_{$columnName}";
        // If index is too long, shorten it to something that very likely will be sufficiently unique
        if (strlen($indexName) > 64) {
            $indexName = 'idx_' . md5($tableName) . '_' . substr(md5($columnName), 0, 27); // 4 + 32 + 1 + 27 = 64
        }
        $this->log->debug("    Creating index '$indexName' ON `{$this->canvasDbName}`.`{$tableName}`(`{$columnName}`)");
        $this->executeIfPersisting("CREATE INDEX {$indexName} ON `{$this->canvasDbName}`.`{$tableName}`(`{$columnName}`)");
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
