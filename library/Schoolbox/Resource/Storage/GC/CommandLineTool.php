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
    /**
     * Print help
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * -n={limit}: attempt to garbage collect this many storage records.
     */
    private const OPT_SHORT_LIMIT = 'n:';

    /**
     * Default number of storage records to clean up.
     */
    private const DEFAULT_LIMIT = 1000;

    /**
     * -t={threshold}: do not attempt to garbage collect records last checked
     * more recently than this time ago.
     */
    private const OPT_SHORT_THRESHOLD = 't:';

    /**
     * Default garbage collection threshold.
     */
    private const DEFAULT_THRESHOLD = '2 months ago';

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
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
Usage: gc-storage.php [-p] [-vvv] [-n{limit}]

    -h, --help:     Print this help text and exit
    -p:             perform: *without* this flag, GC will only do a dry run
    -v[v[v]]:       verbosity: how much activity will be logged
    -n={limit}:     the number of storage records to check, in ascending order of `last_accessed` defaults to 1000
    -t={threshold}: threshold: do not attempt to garbage collect storage records last checked more recently than this
                    date threshold, defaults to 2 months ago.
                    If provided, must be supplied as one of:
                    * a "YYYY-MM-DD HH:MM:SS" date
                    * a timestamp
                    * a relative date value understandable by `strtotime`

HELP;
            exit;
        }

        $limit         = $this->getOptAsInt(self::OPT_SHORT_LIMIT, self::DEFAULT_LIMIT);
        $threshold     = $this->getOpt(self::OPT_SHORT_THRESHOLD);
        $thresholdDate = $this->buildDate($threshold ?? self::DEFAULT_THRESHOLD);
        $db            = $this->getDatabaseInternal();
        $mapper        = StorageMapper::getInstance();
        $gc = new GarbageCollector(
            $mapper,
            $this->getLogger(),
            $limit,
            $thresholdDate,
            $this->perform(),
        );
        $gc = $gc
            ->addCheck(new Check\ForeignKeyRelation($db, SQL_db, $mapper->getTable(), $this->getLogger()))
            ->addCheck(new Check\CharacterField($db, $this->getLogger()))
//            ->addCheck(new Check\HardLinks($db))
            ->addCheck(new Check\SoftLinks($db, SQL_db, $this->getLogger()))
        ;

        $deleted = $gc->purge();

        // if nothing was deleted, consider that an error case?
        exit($deleted ? CliKernelAbstract::EXIT_STATUS_OK : CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
    }
}
