<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * --assessment=[integer]
     *
     * The assessment whose Kami data may be corrupted.
     */
    private const OPT_LONG_ASSESSMENT_ID = 'assessment:';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Kami corrupted data fixer

Usage: fix-corrupted-data.php [--studentSubmissionId] [--perform]

Parameters:
    --assessmentId [integer]
       - Only consider data pertaining to one assessment.
         If not supplied, consider all assessments.
    --perform
       - Include to actually make database changes.
         If not supplied, hypothetical run only.

Examples:
    Find corrupted data in assessment 4:
        fix-corrupted-data.php --assessmentId=4
    Find and fix corrupted data in assessment 4:
        fix-corrupted-data.php --assessmentId=4 --perform
HELP;
            return;
        }

        $assessmentId = $this->getOpt(self::OPT_LONG_ASSESSMENT_ID);

        try {
            $this->fixCorruptedDataProblem1($assessmentId);
            $this->fixCorruptedDataProblem2($assessmentId);
            $this->fixCorruptedDataProblem3($assessmentId);
        } catch (\RuntimeException $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return false;
    }

    /**
     * Fix problem 1:
     *  - Deleted kami_document rows linked to non-deleted student_submission_file rows.
     * Solution:
     *  - If that student_submission_file row does not have a linked non-deleted kami_document row
     *     - Set deleted_at=NULL on that kami_document row
     *    Else make note of problem not fixed.
     */
    private function fixCorruptedDataProblem1(?int $assessmentId): void
    {
        $dbc = $this->getDatabaseInternal();
        $logger = $this->getLogger();
        $quoter = $dbc->getQuoter();

        $query = (
            'SELECT kd.id
            FROM kami_document kd
            JOIN student_submission_file ssf ON (kd.student_submission_file_id = ssf.id)
            JOIN student_submission ss ON (ssf.student_submission_id = ss.id)
            JOIN submission_box sb ON (ss.box_id = sb.id)
            WHERE kd.deleted_at IS NOT NULL
            AND ssf.deleted_at IS NULL'
        );
        $params = [];

        if ($assessmentId !== null) {
            $query .= "\nAND sb.assessment_id = ?";
            $params[] = $assessmentId;
        }

        $kamiDocumentIds = $dbc->executeQuery($query, $params)->fetchAll();
var_dump($kamiDocumentIds);
//        $logger->info();
    }

    private function fixCorruptedDataProblem2(?int $assessmentId): void
    {
    }

    private function fixCorruptedDataProblem3(?int $assessmentId): void
    {
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
