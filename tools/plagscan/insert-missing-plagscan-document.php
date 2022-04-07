#!/usr/bin/php
<?php

/**
 * Finds plagscan documents for similarity check enabled assessment but not in plagscan_document and inserts them
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\ValidFiles;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SubmissionFileMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'since:',
        ];
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return [
            'h',
        ];
    }

    /** @inheritDoc */
    public function printHelp(): void
    {
        $help = "
SYNOPSIS:
    {$this->getToolFilename()} [OPTION]...

DESCRIPTION
    Tool for finding missing student_submission_file_kami data for existing student_submission_file.

    -h, --help
        Print this help and exit

    -p, --perform
        Update DB

    --since=YYYY-MM-DD
        Find data since the date provided. 2021-01-01 by default
";
        \fwrite(STDOUT, $help);
    }


    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt('help') || $this->getOpt('h')) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $plagscanMapper       = PlagscanMapper::getInstance();
        $submissionFileMapper = SubmissionFileMapper::getInstance();

        if (!$plagscanMapper->getConnector()) {
            /* Plagscan not configured - nothing to do
             */
            $this->getLogger()->warning('Plagscan has not been enabled. Aborting');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if ($this->getOpt('since')) {
            $date = new Alaress_Date_Format($this->getOpt('since:'), Alaress_Date_Format::DATABASE_DATE_FORMAT);
        } else {
            $date = new Alaress_Date_Format('2021-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT);
        }

        if (!$this->perform()) {
            $this->getLogger()->notice('Executing in dry run mode. Provide --perform to update DB.');
        }

        $selectQuery =
            "   SELECT student_submission_file.id
                FROM student_submission_file
                    JOIN student_submission ON student_submission_file.student_submission_id = student_submission.id
                    JOIN submission_box ON (student_submission.box_id = submission_box.id AND submission_box.similarity_check_enabled = 1)
                    JOIN storage ON student_submission_file.hash = storage.hash
                    LEFT JOIN plagscan_document ON student_submission_file.id = plagscan_document.student_submission_file_id
                WHERE plagscan_document.id IS NULL
                    AND student_submission_file.deleted_at IS NULL
                    AND student_submission_file.editable_inline = 0
                    AND student_submission_file.created_at >= '{$date->getDatabaseDate()}'
                    AND storage.ext IN ('" . implode("', '", array_keys(ValidFiles::$validDocuments)) . "')";

        $db = $this->getDatabaseInternal();

        $result = $db->executeQuery($selectQuery);
        if ($this->perform()) {
            $this->getLogger()->info('Inserting ' . $result->rowCount() . ' records to plagscan_document.');
            $ids             = $result->fetchColumnArray('id');
            $submissionFiles = $submissionFileMapper->findByColumn('id', $ids);
            /** @var SubmissionFile $submissionFile */
            foreach ($submissionFiles as $submissionFile) {
                $this->getLogger()->info('Queueing Submission File : ' . $submissionFile->getID());
                try {
                    $plagscanMapper->saveNewPlagscanStub($submissionFile);
                    $filename = $plagscanMapper->plagscanFilename($submissionFile);
                    $plagscanMapper->saveNewPlagscan($submissionFile, $filename);
                } catch (\Exception $e) {
                    $this->getLogger()->warning('Failed to queue Submission File : ' . $submissionFile->getID() . ' ' . $e->getMessage());
                }
            }
        } else {
            $this->getLogger()->info($result->rowCount() . ' would be inserted to plagscan_document.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
