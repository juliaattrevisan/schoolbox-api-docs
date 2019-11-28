#!/usr/bin/php
<?php
/**
 * CLI Tool
 *
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

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
Migrate user_id to external_id in schoolbox connector's timetable db tables

Usage: 02-migrate-users.php 

HELP;
            exit;
        }

        /* Find corresponding externalIds for each user_id that exists at the moment
         */
        $this->getDatabaseInternal()->executeQuery('
            UPDATE sis_class_user scu
            INNER JOIN user u ON scu.user_id = u.id
            SET scu.external_id = u.synergy_id;
        ');

        /* Remove anyone who doesn't have an external_id
         */
        $this->getDatabaseInternal()->executeQuery('
            DELETE FROM sis_class_user
            WHERE LENGTH(external_id) = 0 
        ');

        /* Find corresponding externalIds for each teacher_id that exists at the moment
         */
        $this->getDatabaseInternal()->executeQuery('
            UPDATE sis_class_period scp
            INNER JOIN user u ON scp.teacher_id = u.id
            SET scp.teacher_external_id = u.synergy_id   
        ');
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
