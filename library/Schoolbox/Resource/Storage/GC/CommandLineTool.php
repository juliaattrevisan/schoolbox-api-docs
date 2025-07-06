<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Command-line tool for Schoolbox's garbage collector.
 */
class CommandLineTool extends CliTool\App
{
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    private const OPT_SHORT_LIMIT = 'n:';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP, self::OPT_SHORT_LIMIT];
    }

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
     * @inheritdoc
     */
    public function run(): void
    {
        $limit = $this->getOptAsInt(self::OPT_SHORT_LIMIT, 1000);

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
Usage: gc-storage.php [-p] [-vvv] [-n{limit}]

    -h, --help  Print this help text and exit
    -p          perform: *without* this flag, GC will only do a dry run
    -v[v[v]]    verbosity: how much activity will be logged
    -n{limit}   the number of storage records to check, in ascending order of `last_accessed` defaults to 1000

HELP;
            exit;
        }

        $pdo = $this->getDatabaseInternal();
        $sm  = StorageMapper::getInstance();
        $gc  = new GarbageCollector(
            [
                new Check\ExpiryDate($pdo, 2),
                new Check\HardLinks($pdo),
                new Check\SoftLinks($pdo, SQL_db, $this->getLogger()),
            ],
            $sm,
            $this->getLogger(),
            $limit,
            $this->perform()
        );

        $deleted = $gc->purge();

        // if nothing was deleted, consider that an error case?
        exit($deleted ? CliKernelAbstract::EXIT_STATUS_OK : CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
    }
}
