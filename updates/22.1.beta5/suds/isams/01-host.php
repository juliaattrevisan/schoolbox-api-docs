<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
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

iSAMS host Migration

We upgraded the iSAMS configuration/credentials into a modern DSN format
like all the other SUDS connectors. This script forcibly migrates existing
iSAMS credentials into the desired format.

If it's already in the correct format, we don't need to touch it.

If you have problems with this script, you can alternatively reapply the
credentials in  SUDS Control Panel and save it there.

This script always runs in perform mode.

Usage: 01-host.php


HELP;
            exit;
        }

        /* Figure out which suds connector is in use
         */
        $connector = trim((string)$this->getConfig()->get('schooldata_import_data_source'));

        /* Not using isams? Never mind, get out of here
         */
        if (\Schoolbox\External\Importer\Endpoint\Factory::ISAMS !== $connector) {
            return;
        }

        /* This will just be their api host
         */
        $apiHost = trim((string)$this->getConfig()->get('schooldata_import_endpoint_host'));

        /* No api host? No trabajo
         */
        if ('' === $apiHost) {
            return;
        }

        /* Check if the host contains the new format or not
         */
        if (str_contains($apiHost, '/api/batch/1.0/')) {
            $this->getLogger()->debug('iSAMS is using the new format. move along...');
            return;
        }

        $query = "
            UPDATE config
            SET value = :host
            WHERE name = 'schooldata_import_endpoint_host'
            LIMIT 1
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            'host' => sprintf("%s/api/batch/1.0/", $apiHost)
        ]);

        $this->getLogger()->debug('iSAMS is using the old credentials format; upgrading it to modern host format');
    }


    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
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
