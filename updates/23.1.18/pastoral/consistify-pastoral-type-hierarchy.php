<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * if staging, dev or trial, clear intellischool secret config.
 */
$app = new class extends CliTool\App {
    /**
     * --help: print help
     */
    private const OPT_LONG_HELP = 'help';

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * @return void
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find and fix pastoral records whose type/subtype does not match any current type/subtype pairing in the
lu_pastoral_subtype table.  Fix by updating the type, not the subtype.

Run without the --perform parameter to see what would happen if --perform was used.

Usage: consistify-pastoral-type-hierarchy.php [--perform]

HELP;
            exit;
        }

        $dbc = $this->getDatabaseInternal();
        $log = $this->getLogger();

        // Pseudocode
        // ----------
        // Get current pastoral subtype/type pairs
        // Foreach pair,
        //  * find whether any pastoral records exist with the same subtype but different type
        //  * if any found do exist,
        //     * update pastoral record type to match type from pair

        $pastoralTypeIdBySubtypeId = $dbc->executeQuery(
            'SELECT id AS subtypeId, pastoral_type_id AS typeId
            FROM lu_pastoral_subtype',
        )->fetchColumnArray('typeId', 'subtypeId');
        $log->info('Found ' . count($pastoralTypeIdBySubtypeId) . ' current pastoral type/subtype pairs.');

        $log->info('Finding and fixing pastoral records with mismatched type/subtype...');
        $totalMismatchedRecords = 0;
        foreach ($pastoralTypeIdBySubtypeId as $subtypeId => $typeId) {
            $log->info(" * Finding pastoral records with subtype $subtypeId, and type not $typeId.");
            $pastoralIdsWithMismatchedTypes = $dbc->executeQuery(
                // Deliberately include soft-deleted pastoral records here, to ensure that after this script has run,
                // no records will have invalid type/subtype combination (with no exception).
                'SELECT id
                FROM pastoral
                WHERE subtype_id = :subtypeId
                AND type_id <> :typeId',
                ['subtypeId' => $subtypeId, 'typeId' => $typeId]
            )->fetchColumnArray('id');

            $nMismatchedRecords = count($pastoralIdsWithMismatchedTypes);

            if ($nMismatchedRecords === 0) {
                continue;
            }

            $log->info("   Found $nMismatchedRecords pastoral record(s).");

            $totalMismatchedRecords += $nMismatchedRecords;

            if ($this->perform()) {
                try {
                    $statement = $dbc->executeQuery(
                        'UPDATE pastoral
                        SET type_id = :typeId
                        WHERE id IN (
                            ' . implode(',', array_map('intval', $pastoralIdsWithMismatchedTypes)) . '
                        )',
                        ['typeId' => $typeId]
                    );
                } catch (\PDOException $e) {
                    $log->error('   Update query failed - ' . $e->getMessage());
                    $log->info('   Continuing to next type/subtype pair...');
                    continue;
                }
                $nRowsAffected = $statement->rowCount();

                $log->info("   Updated $nRowsAffected rows.");
            } else {
                $log->info('   No update, because not in --perform mode.');
            }
        }

        if ($totalMismatchedRecords === 0) {
            $log->info(' * Found no mismatched pastoral records.');
        }

        $log->info("Processed a total of $totalMismatchedRecords pastoral records with mismatched type/subtype.");

        if (!$this->perform()) {
            $log->info('BUT PERFORMED NO UPDATES, BECAUSE --perform NOT USED');
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]))->runApp();
