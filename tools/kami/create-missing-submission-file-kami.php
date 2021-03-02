#!/usr/bin/php
<?php
/**
 * Finds student submission files that are supported by Kami but not in student_submission_file_kami and insert them
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami\Model as Kami;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'since:',
        ];
    }

    public function run(): void
    {
        if ($this->getOpt('since')) {
            $date = new Alaress_Date_Format($this->getOpt('since:'), Alaress_Date_Format::DATABASE_DATE_FORMAT);
        } else {
            $date = new Alaress_Date_Format('2021-01-01', Alaress_Date_Format::DATABASE_DATE_FORMAT);
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
        echo $query;

        $db = $this->getDatabaseInternal();

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
