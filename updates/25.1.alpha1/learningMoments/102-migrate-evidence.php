<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

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

Learning Moments Migration

This converts the 1:1 relationship between moments to the 1:n relationship it always represented,
separating the Learning Moment from all the pieces of supporting Evidence (files). A Learning Moment
is related to multiple Evidence.

To do that we move all the hash/filename/type values from `moment` to `moment_evidence`.

This always runs in perform mode as part of the upgrade.

Usage: 102-migrate-evidence.php


HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        /* We're going be doing this more than a few times: prepare it only once, for speed
         */
        $evidenceStatement = $db->prepareQuery("
            INSERT INTO moment_evidence (moment_id, type, hash, filename, created_at)
            VALUES (:momentId, :type, :hash, :filename, :createdAt);
        ");

        /* Find all the moments we need to update including all the soft-deleted ones
         * You never know when they're going to respawn
         */
        $query = "
            SELECT id, hash, filename, type, created_at
            FROM moment
            WHERE hash IS NOT NULL
            AND filename IS NOT NULL
        ";

        $moments = $db->query($query)->fetchAll();

        try {
            foreach ($moments as $moment) {
                /* Move it! We json-encode this for logging reasons
                 */
                $evidence = [
                    'momentId' => $moment['id'],
                    'type' => $moment['type'],
                    'hash' => $moment['hash'],
                    'filename' => $moment['filename'],
                    'createdAt' => $moment['created_at'],
                ];

                $this->getLogger()->debug(sprintf(
                    'Updating learning moment %s to have new moment_evidence %s',
                    $moment['id'],
                    json_encode($evidence)
                ));

                $evidenceStatement->execute($evidence);

                $this->getLogger()->info(sprintf(
                    'Updated learning moment %s to have new moment_evidence',
                    $moment['id'],
                ));
            }
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
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
        return parent::runAsRoot();
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
