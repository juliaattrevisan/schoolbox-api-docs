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
        private array $loadedRelations = [];
        private array $relationsTree = [];
        private IStatement $query;

        public function run(): void
        {
            global $argv;
            $baseTable = $argv[1] ?? null;
            if (!$baseTable) {
                echo "Usage: build-fk-graph.php <baseTable>\n";
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }
            $this->query = $this->getDatabaseInternal()->prepareQuery("
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


            $this->relationsTree = [
                $baseTable => [],
            ];
            $this->buildRelationsTree($baseTable, $this->relationsTree[$baseTable]);
            $this->outputRelationsTree($this->relationsTree);
        }

        private function buildRelationsTree(string $table, array &$location): void
        {
            // Don't reload relations if already loaded
            if (isset($this->loadedRelations[$table])) {
                return;
            }
            $this->loadedRelations[$table] = true;

            $this->query->execute(['schema' => SQL_db, 'table' => $table]);
            $joinedTables = $this->query->fetchColumnArray('table');
            $this->loadedRelations[$table] = $joinedTables;

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
