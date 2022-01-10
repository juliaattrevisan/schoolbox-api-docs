#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

/**
 * Tool to increment data dates for sandbox instances
 */
$app = new class extends CliTool\App {
    private const BLACKLISTED_COLUMNS = [
//        'table' => [
//            'column',
//        ]
    ];

    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function run(): void
    {
        // Get epoch date increment config date_increment_epoch
        $query = $this->getDatabaseInternal()->prepareQuery("SELECT value FROM config WHERE name = :configName");
        $query->execute(['configName' => 'date_increment_epoch']);
        $epoch_config = $query->fetchAll(PDO::FETCH_COLUMN)[0];

        if (!$epoch_config) {
            $this->getLogger()->notice("'date_increment_epoch' not set. Exiting.");
            exit;
        }
        $epochDate = new Alaress_Date_Format($epoch_config, Alaress_Date_Format::DATABASE_DATE_FORMAT);

        $diffDays = floor(($epochDate->diffInDays(Alaress_Date_Format::now(), false) / 7)) * 7;
        if (!$diffDays) {
            $this->getLogger()->notice("Day diff: $diffDays. There's nothing to do here just yet. Exiting.");
            exit;
        }
        $this->getLogger()->notice("Incrementing dates by $diffDays days.");
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->incrementDates((int)$diffDays);
        } catch (\Throwable $e) {
            $this->getLogger()->error("Exception caught. Rolling back transaction.");
            $this->getDatabaseInternal()->rollBack();
            throw $e;
        }
        $this->getDatabaseInternal()->commit();
    }


    /**
     * Increments all dates in database by specified number
     */
    private function incrementDates(int $diffDays): void
    {
        // Fetch all tables
        $query = $this->getDatabaseInternal()->executeQuery(
            "
                SELECT c.TABLE_NAME, c.COLUMN_NAME
                FROM INFORMATION_SCHEMA.TABLES t
                JOIN INFORMATION_SCHEMA.COLUMNS c ON c.TABLE_NAME = t.TABLE_NAME
                WHERE t.TABLE_SCHEMA = :schema
                AND TABLE_TYPE = 'BASE TABLE'
                AND c.DATA_TYPE IN ('date','datetime')
                ORDER BY c.TABLE_NAME, c.COLUMN_NAME
            ",
            ['schema' => $this->getDatabaseInternal()->getDatabase()],
        );
        foreach ($query->fetchAll(PDO::FETCH_NUM) as [$table, $column]) {
            $this->incrementColumn($table, $column, $diffDays);
        }
        $this->getDatabaseInternal()->executeQuery(
            "
                UPDATE config
                SET value = DATE_ADD(value, INTERVAL :diff DAY)
                WHERE name = :configName
            ",
            [
                'diff'       => $diffDays,
                'configName' => 'date_increment_epoch',
            ],
        );
    }


    /**
     * Increments column by specified number of days
     *
     * @param string $tableName
     * @param string $column
     * @param int    $diffDays
     */
    private function incrementColumn(string $tableName, string $column, int $diffDays): void
    {
        // Is column blacklisted?
        // @phpstan-ignore-next-line: whilst blacklisted columns are currently empty, they may not be forever
        if (isset(self::BLACKLISTED_COLUMNS[$tableName]) && in_array($column, self::BLACKLISTED_COLUMNS[$tableName])) {
            return;
        }

        // If column is part of index we need to avoid creating a temporary collision.
        // If increasing the value, need to update the largest first.
        // Else decreasing the value, need to update the smallest first.
        $order = $diffDays > 0
            ? 'DESC'
            : 'ASC';

        $this->getLogger()->info("Incrementing dates for: $tableName.$column");
        $updateQuery = $this->getDatabaseInternal()->prepareQuery(
            "
            UPDATE $tableName
            SET $column = DATE_ADD($column, INTERVAL :diff DAY)
            ORDER BY $column $order
        "
        );
        $updateQuery->execute([':diff' => $diffDays]);
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
