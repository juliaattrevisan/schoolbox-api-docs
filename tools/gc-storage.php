#!/usr/bin/php
<?php

/**
 * Usage: ./gc-storage.php [-p] [-vvv] [-n{limit}]
 *
 * This script should be run in a cron, depending on the school, anywhere from weekly to nightly.
 *
 *  -p          perform: *without* this flag, GC will only do a dry run
 *  -v[v[v]]    verbosity: how much activity will be logged
 *  -n{limit}   the number of storage records to check, in ascending order of `last_accessed`
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Lumberjack\Logger;
use Schoolbox\Database\Factory;
use Schoolbox\Resource\Storage\GC;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

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
     * Hands off execution over to the App
     */
    public function run(): void
    {
        $opts    = getopt('pvn:');
        $limit   = isset($opts['n']) ? (int)$opts['n'] : 1000;
        $perform = isset($opts['p']);

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP
Usage: gc-storage.php [-p] [-vvv] [-n{limit}]

    -p          perform: *without* this flag, GC will only do a dry run
    -v[v[v]]    verbosity: how much activity will be logged
    -n{limit}   the number of storage records to check, in ascending order of `last_accessed` defaults to 1000

HELP;
            exit;
        }

        $pdo = Factory::getInstanceInternal();

        $threshold = 0;
        if (isset($opts['v'])) {
            $threshold = 1;
            if (is_array($opts['v'])) {
                $threshold = min(3, count($opts['v']));
            }
        }

        $limits = [
            0 => 500, // nil verbosity: exceptional behaviour only
            1 => 250, // min verbosity: significant events
            2 => 200, // mod verbosity: most things
            3 => 100  // max verbosity: EV-ER-Y-THING...
        ];

        $sm = StorageMapper::getInstance();
        $log = new Logger(STDOUT, STDERR, $limits[$threshold]);
        $gc = new GC\GarbageCollector(
            [
                new GC\Check\ExpiryDate($pdo, 2),
                new GC\Check\HardLinks($pdo),
                new GC\Check\SoftLinks($pdo, SQL_db, $log),
            ],
            $sm,
            $log,
            $limit,
            $perform
        );

        $deleted = $gc->purge();

        // if nothing was deleted, consider that an error case
        exit($deleted ? 0 : 1);
    }
};

$kernel = new CliTool\Kernel($app, [
        CliTool\Kernel::OPTION_ERRORS => E_ALL
    ]);
$kernel->runApp();
