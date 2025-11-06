#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Alaress\Database\Statement\IStatement;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new Kernel(
    new class extends App {
        /**
         * Map of relations already loaded: added so that recursive relations
         * are not loaded in an infinite loop.
         *
         * @var array<string, true>
         */
        private array $loadedRelations = [];

        /**
         * Query for querying list of tables with an FK to a given table.
         *
         * @var IStatement
         */
        private IStatement $selectRelatedTablesQuery;

        /**
         * @return void
         */
        public function run(): void
        {
            global $argc, $argv;
            if (count($argv) < 2) {
                echo "Usage: build-fk-graph.php [table] [table...]\n";
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }
            $baseTables = array_slice($argv, 1);
            $selectBaseTableQuery = $this->getDatabaseInternal()->prepareQuery("
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = :schema
                  AND TABLE_NAME   = :table
            ");
            $this->selectRelatedTablesQuery = $this->getDatabaseInternal()->prepareQuery("
                SELECT DISTINCT
                    fks.TABLE_NAME AS `table`
                FROM information_schema.REFERENTIAL_CONSTRAINTS fks
                INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
                    ON  fks.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
                    AND fks.TABLE_NAME        = kcu.TABLE_NAME
                    AND fks.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
                INNER JOIN information_schema.TABLES t
                    ON  kcu.TABLE_SCHEMA = t.TABLE_SCHEMA
                    AND kcu.TABLE_NAME   = t.TABLE_NAME
                WHERE fks.CONSTRAINT_SCHEMA        = :schema
                  AND fks.UNIQUE_CONSTRAINT_SCHEMA = :schema
                  AND fks.REFERENCED_TABLE_NAME    = :table
                ORDER BY fks.TABLE_NAME
            ");

            $relationsTree = [];
            foreach ($baseTables as $baseTable) {
                // Verify table exists
                $selectBaseTableQuery->execute(['schema' => SQL_db, 'table' => $baseTable]);
                if ($selectBaseTableQuery->rowCount() === 0) {
                    $this->getLogger()->warning("Table '$baseTable' does not exist: skipping");
                    continue;
                }

                // Build its tree
                $relationsTree[$baseTable] ??= [];
                $this->buildRelationsTree($baseTable, $relationsTree[$baseTable]);
            }
            $this->outputRelationsTree($relationsTree);
        }

        /**
         * Build a tree of relations for a given table.
         *
         * @param string $table     Table to build a tree for.
         * @param array  &$location Where in the previously-built tree to store
         *                          this table's tree.
         * @return void
         */
        private function buildRelationsTree(string $table, array &$location): void
        {
            // Don't reload relations if already loaded
            if (isset($this->loadedRelations[$table])) {
                return;
            }
            $this->loadedRelations[$table] = true;

            // Fetch its relations
            $this->selectRelatedTablesQuery->execute(['schema' => SQL_db, 'table' => $table]);
            $joinedTables = $this->selectRelatedTablesQuery->fetchColumnArray('table');
            $this->loadedRelations[$table] = $joinedTables;

            // Build the relation tree
            foreach ($joinedTables as $joinedTable) {
                $location[$joinedTable] = [];
                $loadedRelation = $this->loadedRelations[$joinedTable] ?? null;
                if (!empty($loadedRelation)) {
                    // Non-empty relation already loaded?
                    // Say it's a repeat
                    $location[$joinedTable]['(repeated relation)'] = [];
                } elseif (isset($this->loadedRelations[$joinedTable])) {
                    // Empty relation already loaded?
                    // Say it's there, but nothing else
                    $location[$joinedTable] = [];
                } else {
                    // New relation?
                    // Build its tree
                    $this->buildRelationsTree($joinedTable, $location[$joinedTable]);
                }
            }
        }

        /**
         * Output the fully-built tree.
         *
         * @param array $relationsTree
         * @param int   $depth
         * @return void
         */
        private function outputRelationsTree(array $relationsTree, int $depth = 0): void
        {
            foreach ($relationsTree as $table => $relations) {
                printf(
                    "%s%s%s\n",
                    str_repeat('-', $depth),
                    ($depth === 0 ? '' : ' '),
                    $table,
                );
                $this->outputRelationsTree($relations, $depth + 1);
            }
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL
    ]
);
$kernel->runApp();
