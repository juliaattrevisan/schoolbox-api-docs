#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database;
use Schoolbox\Application\CliTool;
use Schoolbox\YearLevel;

require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * Populate new lu_year_level with distinct values from user.year
 */
$app = new class extends CliTool\App
{
    /** Hands off execution over to the App */
    public function run(): void
    {
        $this->getDatabaseInternal()->beginTransaction();
        try {
            $this->copyUserYearLevels();
            $this->getDatabaseInternal()->commit();
        } catch (Database\Exception $e) {
            $this->getLogger()->error('Database error, rolling back transaction', ['exception' => $e]);
            $this->getLogger()->error($e->getMessage());
            $this->getLogger()->error($e->getTraceAsString());
            $this->getDatabaseInternal()->rollBack();
        }
    }


    /**
     * Find and copy user's distinct year levels to lu_year_level
     */
    private function copyUserYearLevels(): void
    {
        $yearLevels = $this->getDatabaseInternal()->executeQuery("
            SELECT DISTINCT year
            FROM user
            WHERE 
                  -- only fetch non-empty values from non-deleted users
                  year IS NOT NULL AND 
                  TRIM(year) != '' AND 
                  deleted_at IS NULL AND
                  -- Idempotent query (ignore years already present in table)
                  year NOT IN (SELECT DISTINCT name FROM lu_year_level WHERE deleted_at IS NULL);
        ")->fetchAll(PDO::FETCH_COLUMN);

        $this->getLogger()->info(sprintf("%s user year levels found to copy", count($yearLevels) ?: 'No'));

        uasort($yearLevels, 'strnatcasecmp');

        $statement = $this->getDatabaseInternal()->prepareQuery("
            INSERT INTO lu_year_level (name, sequence, created_at, updated_at) VALUES (
                :name, 
                (SELECT nextSequence FROM (SELECT IF(MAX(sequence) IS NULL, :firstSequence, MAX(sequence) + 1) nextSequence FROM lu_year_level WHERE deleted_at IS NULL) t1), 
                :created_at,
                :updated_at
            )
        ");

        $nowDatabase = Alaress_Date_Format::now()->getDatabase();
        foreach ($yearLevels as $yearLevel) {
            $this->getLogger()->info("Inserting new year level '$yearLevel'");
            $statement->execute([
                    'name'          => $yearLevel,
                    'firstSequence' => YearLevel\Model::sequenceBase(),
                    'created_at'    => $nowDatabase,
                    'updated_at'    => $nowDatabase,
                ]);
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
