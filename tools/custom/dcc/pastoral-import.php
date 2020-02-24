#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
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
DCC Import pastoral records with attachments

Usage: pastoral-import.php

HELP;
            exit;
        }

        $this->processData();
    }


    /**
     * Process all the data
     */
    private function processData()
    {
        $externalDatabase = Schoolbox\Database\Factory::getInstanceExternal();

        if (!$externalDatabase) {
            $this->getLogger()->critical('Null external database for DCC Pastoral Importer; exiting');

            return;
        }

        $statement = $externalDatabase->prepareQuery("
            SELECT DISTINCT
                vd.ID, vd.Description, vd.DocumentCreatedDate, vd.DocumentType, vd.Document
            FROM dbo.media.vDocuments vd
            INNER JOIN dbo.vStudents vs ON vd.ID = vs.ID
            WHERE ClassificationCode = 'LearningSupport' and vs.StudentCampus = 'P'
        ");

        $statement->execute();

        $summary = [
            'success' => 0,
            'failure' => 0
        ];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* Manufacture a pastoral record
             */

            /* Assign values to pastoral record
             */

            /* If the assigned user(s) couldn't be found, use a fallback user
             * I guess that should be a config var if it isn't already one
             */

            /* Accept raw data into storage->accept()
             */

            /* Assign the storage file to the pastoral record
             */

            /* Save it
             */

            /* Log it
             */
        }
    }
};

 $options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
