#!/usr/bin/php
<?php

/**
 * Finds student submission files that are supported by Kami but not in student_submission_file_kami and insert them
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami\Model as Kami;

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

        $connector = new \Schoolbox\Learning\InlineGrader\Kami\Connector(
            new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]),
            $this->getConfig()->get('kami_enabled'),
            $this->getConfig()->get('kami_api_key'),
            $this->getConfig()->get('site_school_code')
        );

        if (!$connector->isEnabled()) {
            $this->getLogger()->warning('Kami has not been enabled. Aborting');
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

        $insertQuery = "INSERT INTO student_submission_file_kami (
                    student_submission_file_id,
                    status,
                    created_at,
                    updated_at
                  ) ";
        $selectQuery =
            "   SELECT
                    student_submission_file.id,
                    '" . Kami::STATUS_QUEUED . "',
                    now(),
                    now()
                FROM student_submission_file
                    JOIN storage ON student_submission_file.hash = storage.hash
                    LEFT JOIN student_submission_file_kami ON student_submission_file.id = student_submission_file_kami.student_submission_file_id
                WHERE student_submission_file_kami.id IS NULL
                    AND student_submission_file.deleted_at IS NULL
                    AND student_submission_file.created_at >= '{$date->getDatabaseDate()}'
                    AND storage.ext IN ('" . implode("', '", array_keys(Kami::ALLOWED_FILE_TYPES)) . "')";

        $query = $this->perform() ? $insertQuery . $selectQuery : $selectQuery;
        $db    = $this->getDatabaseInternal();

        $result = $db->executeQuery($query);
        if ($this->perform()) {
            $this->getLogger()->info($result->rowCount() . ' inserted to student_submission_file_kami.');
        } else {
            $this->getLogger()->info($result->rowCount() . ' would be inserted to student_submission_file_kami.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
