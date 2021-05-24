#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 */
$app = new class extends CliTool\App {
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';


    // --hard-delete: hard delete orphans
    private const OPT_LONG_HARD_DELETE = 'hard-delete';


    // --include-soft-deleted: include already soft-deleted orphans
    private const OPT_LONG_INCLUDE_SOFT_DELETED = 'include-soft-deleted';


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_HARD_DELETE,
            self::OPT_LONG_INCLUDE_SOFT_DELETED,
        ];
    }


    /**
     * Output command-line help text.
     */
    private function outputHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        echo <<<HELP
$white
NAME:
    Assessment Orphan Cleanup tool

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Reports on and optionally deletes orphaned assessments.

    (Orphaned assessments are assessments which have no relation to a due work,
     quiz, task or project.)

    $yellow-h, --help$white
        Print this help and exit.

    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, eg -vvv.

    $yellow-p$white
        Actually performs the deletion: instead, simply reports on what would be
        deleted.

    $yellow--hard-delete$white
        If provided, attempts to hard-delete assessments.

        This tool soft-deletes assessments by default, because hard-deletion
        requires the deletion of several relations first.

    $yellow--include-soft-deleted$white
        If provided, attempts to delete already soft-deleted assessments.

        Only makes sense when used with the $yellow--hard-delete$white flag.


HELP;
    }


    /**
     * Run the cli tool.
     */
    public function run(): void
    {
        // Help output
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->outputHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }


        // Perform/hard-delete messages
        if (!$this->perform()) {
            $this->getLogger()->info('Running in dry-run mode: run with the `-p` flag to perform changes');
        }

        if ($this->isHardDelete()) {
            $this->getLogger()->warning(
                'Running in hard-delete mode: this will irrecoverably delete orphans and their relations!'
            );
        }


        // Run
        [$successCount, $failureCount] = $this->runCleanup();


        // Summarize
        $message = $this->perform()
            ? '%d orphaned assessments have been deleted'
            : '%d orphaned assessments would have been deleted';
        $this->getLogger()->info(sprintf($message, $successCount));
        if ($failureCount > 0) {
            $this->getLogger()->error(sprintf('%d orphaned assessments could not be deleted', $failureCount));
        }
    }


    /**
     * Returns a list of orphaned assessments.
     *
     * Each value in the return array has an "id" and "title" key.
     *
     * @return array
     */
    private function findOrphanedAssessments(): array
    {
        $selectQuery = "
            SELECT a.id, a.title
            FROM  assessment a
            LEFT JOIN submission_box d ON a.id = d.assessment_id
            LEFT JOIN assess         q ON a.id = q.assessment_id
            LEFT JOIN task           t ON a.id = t.assessment_id
            LEFT JOIN project        p ON a.id = p.assessment_id
            WHERE d.assessment_id IS NULL
            AND   q.assessment_id IS NULL
            AND   t.assessment_id IS NULL
            AND   p.assessment_id IS NULL
        ";
        if (!(bool)$this->getOpt(self::OPT_LONG_INCLUDE_SOFT_DELETED)) {
            $selectQuery .= ' AND a.deleted_at IS NULL';
        }
        $selectQuery .= ' ORDER BY a.id';


        $select = $this->getDatabaseInternal()->prepareQuery($selectQuery);
        $select->execute();
        return $select->fetchAll();
    }


    /**
     * Returns a list of delete orphaned assessment queries.
     *
     * The list is different for hard and for soft deletion: soft-delete just
     * soft-deletes the assessment, whilst hard-delete must delete all relations
     * as well (except for relations to assessment types: orphans don't have
     * these relations anyway).
     *
     * @return IQuery[]
     */
    private function buildDeleteQueries(): array
    {
        $softDeleteQueries = [
            "UPDATE assessment SET deleted_at = :deletedAt WHERE id = :id",
        ];
        $hardDeleteQueries = [
            "DELETE FROM assessment_folder WHERE assessment_id = :id",
            "DELETE FROM assessment_course_unit_implementation WHERE assessment_id = :id",
            "DELETE FROM assessment_course_unit_specification WHERE assessment_id = :id",
            "DELETE FROM project_assessment WHERE assessment_id = :id",
            "DELETE FROM assessment_participant WHERE assessment_id = :id",
            "
                DELETE p
                FROM   participant p
                INNER JOIN participant_plan pp on p.participant_plan_id = pp.id
                WHERE pp.assessment_id = :id
            ",
            "DELETE FROM participant_plan WHERE assessment_id = :id",
            "DELETE FROM assessment_specification_implementation WHERE specification_assessment_id = :id",
            "DELETE FROM assessment_specification_implementation WHERE implementation_assessment_id = :id",
            "DELETE FROM curriculum_node_for_assessment WHERE assessment_id = :id",
            "DELETE FROM assessment WHERE id = :id",
        ];


        /** @var IQuery[] $deleteQueries */
        $deleteQueries = [];
        $db            = $this->getDatabaseInternal();
        foreach (($this->isHardDelete() ? $hardDeleteQueries : $softDeleteQueries) as $deleteQuery) {
            $deleteQueries[] = $db->prepareQuery($deleteQuery);
        }
        return $deleteQueries;
    }


    /**
     * Builds the query parameters for the queries defined in
     * `buildDeleteQueries`.
     *
     * @param int                 $id
     * @param Alaress_Date_Format $now
     * @return int[]
     */
    private function buildDeleteQueryParams(int $id, Alaress_Date_Format $now): array
    {
        $params = ['id' => $id,];
        if (!$this->isHardDelete()) {
            $params['deletedAt'] = $now->getDatabase();
        }
        return $params;
    }


    /**
     * Runs the cleanup for all orphans.
     *
     * @return int[] [$successCount, $failureCount]
     */
    private function runCleanup(): array
    {
        // Actually run the cleanup
        $successCount  = 0;
        $failureCount  = 0;
        $now           = Alaress_Date_Format::now();
        $deleteQueries = $this->buildDeleteQueries();
        foreach ($this->findOrphanedAssessments() as $row) {
            $id = (int)$row['id'];
            $this->getLogger()->debug(sprintf("Deleting orphaned assessment #%d (%s)", $id, $row['title']));

            // Transact this: rollback if dry-run or query failed
            $this->getDatabaseInternal()->beginTransaction();
            try {
                foreach ($deleteQueries as $deleteQuery) {
                    $deleteQuery->execute($this->buildDeleteQueryParams($id, $now));
                }
            } catch (Throwable $e) {
                $failureCount++;
                $this->getLogger()->error(sprintf('Orphaned #%d could not be deleted: %s', $id, $e->getMessage()));
                $this->getDatabaseInternal()->rollBack();
                continue;
            }

            $successCount++;
            $this->perform()
                ? $this->getDatabaseInternal()->commit()
                : $this->getDatabaseInternal()->rollBack();
        }
        return [$successCount, $failureCount];
    }


    /**
     * Is this cleanup a hard-delete?
     *
     * @return bool
     */
    private function isHardDelete(): bool
    {
        return (bool)$this->getOpt(self::OPT_LONG_HARD_DELETE);
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO,]);
$kernel->runApp();
