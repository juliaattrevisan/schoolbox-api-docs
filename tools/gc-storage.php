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

use Schoolbox\Resource\Storage\GC;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

$app = new class extends CliTool\App
{
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    private const OPT_SHORT_LIMIT = 'n:';

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP, self::OPT_SHORT_LIMIT];
    }

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
        $limit = $this->getOptAsInt(self::OPT_SHORT_LIMIT, 1000);

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
Usage: gc-storage.php [-p] [-vvv] [-n{limit}]

    -p          perform: *without* this flag, GC will only do a dry run
    -v[v[v]]    verbosity: how much activity will be logged
    -n{limit}   the number of storage records to check, in ascending order of `last_accessed` defaults to 1000

HELP;
            exit;
        }

        $pdo = $this->getDatabaseInternal();

        $sm = StorageMapper::getInstance();
        $gc = new GC\GarbageCollector(
            [
                new GC\Check\ExpiryDate($pdo, 2),
                new GC\Check\HardLinks($pdo),
                new GC\Check\SoftLinks($pdo, SQL_db, $this->getLogger()),
            ],
            $sm,
            $this->getLogger(),
            $limit,
            $this->perform()
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
