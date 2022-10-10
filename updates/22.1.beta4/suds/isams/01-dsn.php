<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
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
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const MY_TOOL_LOCK_NAME = 'isams_dsn_migration';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


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

iSAMS DSN Migration

We upgraded the iSAMS configuration/credentials into a modern DSN format
like all the other SUDS connectors. This script forcibly migrates existing
iSAMS credentials into the desired format.

If it's already in the correct format, we don't need to touch it.

If you have problems with this script, you can alternatively reapply the 
credentials in  SUDS Control Panel and save it there.

This script always runs in perform mode.

Usage: 01-dsn.php


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

        /* This will just be their api key, not the dsn format
         * If we cannot decode it, save it in a format that *can* decode
         *
         * Otherwise, if it decoded successfully, they already updated it manually
         * and we don't need to change it here
         */
        $apiKey = trim((string)$this->getConfig()->get('schooldata_import_endpoint_key'));

        /* No api key? No trabajo
         */
        if ('' === $apiKey) {
            return;
        }

        /* api key exists so let's see if we can reformat it
         */
        $codec = new \Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN();

        try {
            $decoded = $codec->decode($apiKey);
        } catch (\DomainException | \ErrorException $e) {
            /* Could not decode the $apiKey, so it's likely to be in the wrong format
             * or it isn't set at all
             *
             * Update the DSN as best we can to reflect the current configuration
             */
            $dsn = $codec->encode([
                'modules' => [
                    'batchxml' => [
                        'key' => $apiKey,
                    ]
                ]
            ]);

            $query = "
                UPDATE config 
                SET value = :dsn 
                WHERE name = 'schooldata_import_endpoint_key'
                LIMIT 1
            ";

            $statement = $this->getDatabaseInternal()->prepareQuery($query);
            $statement->execute([
                'dsn' => $dsn
            ]);

            $this->getLogger()->debug('iSAMS is using the old credentials format; upgrading it to modern DSN');
        }
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
