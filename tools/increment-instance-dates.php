#!/usr/bin/php

<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Tool to increment data dates for sandbox instances
 */
$app = new class extends CliTool\App
{
    /** @inheritDoc */
    public function run(): void
    {
        $blackListedColumns = [
//            'table_name' => [
//                'datetime_column',
//            ]
        ];

        $db     = $this->getDatabaseInternal();

        // Get epoch date increment config date_increment_epoch
        $query  = $db->prepareQuery("SELECT value FROM config WHERE name = :configName");
        $query->execute(['configName' => 'date_increment_epoch']);
        $epoch_config = $query->fetch(PDO::FETCH_COLUMN);

        $diff = floor((time() - $epoch_config) / 7) * 7;

        if ($diff === floatval(0)) {
            echo "There's nothing to do here just yet. Exiting...\n";
            exit;
        } else {
            // Fetch all tables
            $query  = $db->prepareQuery("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db
                                                       AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
            $query->execute(['db' => $db->getDatabase()]);
            $tables = $query->fetchAll();

            foreach ($tables as $table) {
                $tableName = $table['TABLE_NAME'];
                // Select all datetime column
                $columnsQuery = $db->prepareQuery("SHOW COLUMNS FROM " . $tableName . " WHERE Type = 'datetime'");
                $columnsQuery->execute();

                $columns = $columnsQuery->fetchAll();

                foreach ($columns as $column) {
                    // Is column blacklisted?
                    if (isset($blackListedColumns[$tableName]) && in_array($column['Field'], $blackListedColumns[$tableName])) {
                        continue;
                    }
                    $field = $column['Field'];

                    $this->getLogger()->info("Incrementing dates for: " . $tableName . "." . $field);
                    $updateQuery = $db->prepareQuery("UPDATE " . $tableName . " SET " . $field . " = DATE_ADD(" . $field . ", INTERVAL :diff SECOND)");
                    $updateQuery->execute([':diff' => $diff]);
                }
            }
        }
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
