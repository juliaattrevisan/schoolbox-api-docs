<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Instance;
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
     * --force-run-on-staging: unless this flag is provided, do not allow this
     * tool to run on staging instances.
     */
    private const OPT_LONG_FORCE_RUN_ON_STAGING = 'force-run-on-staging';

    /**
     * Default garbage collection threshold.
     */
    private const DEFAULT_THRESHOLD = '2 months ago';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_LIMIT,
            self::OPT_SHORT_THRESHOLD,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FORCE_RUN_ON_STAGING,
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

    -h, --help:             Print this help text and exit
    -p:                     perform: *without* this flag, GC will only do a dry run
    -v[v[v]]:               verbosity: how much activity will be logged
    -n={limit}:             the number of storage records to check, in ascending order of `last_accessed` defaults to
                            1000.
    -t={threshold}:         threshold: do not attempt to garbage collect storage records last checked more recently than
                            this date threshold, defaults to 2 months ago.
                            If provided, must be supplied as one of:
                            * a "YYYY-MM-DD HH:MM:SS" date
                            * a timestamp
                            * a relative date value understandable by `strtotime`
    --force-run-on-staging: unless this flag is provided, this tool will not run on a staging instance.
                            Since staging and production instances will likely share file storage, deleting a file from
                            staging will delete it from production as well.
                            If this flag is provided, the run must be further confirmed by answering a prompt.

HELP;
            exit;
        }

        $logger = $this->getLogger();
        if ($this->perform()) {
            /** @var Instance $instance */
            $instance = $this->getDi()->get('instance');
            if ($instance->isStaging()) {
                $forceRunOnStaging = $this->getOpt(self::OPT_LONG_FORCE_RUN_ON_STAGING);
                if ($forceRunOnStaging) {
                    $logger->warning('Running in perform mode on a staging instance: this could delete data on the production instance!');
                    while (true) {
                        $continue = strtolower($this->readLine('Continue? [y/N]: '));
                        if ($continue === 'y') {
                            break;
                        }
                        if ($continue === 'n') {
                            exit(CliKernelAbstract::EXIT_STATUS_OK);
                        }
                    }
                } else {
                    $logger->notice('Refusing to run in perform mode on a staging instance without the --force-run-on-staging flag.');
                    exit(CliKernelAbstract::EXIT_STATUS_OK);
                }
            } else {
                $logger->notice('Running in perform mode: this will permanently garbage collect files.');
            }
        } else {
            $logger->notice('Running in dry-run mode: use -p flag to permanently garbage collect files.');
        }

        $limit         = $this->getOptAsInt(self::OPT_SHORT_LIMIT, self::DEFAULT_LIMIT);
        $threshold     = $this->getOpt(self::OPT_SHORT_THRESHOLD);
        $thresholdDate = $this->buildDate($threshold ?? self::DEFAULT_THRESHOLD);
        $db            = $this->getDatabaseInternal();
        $mapper        = StorageMapper::getInstance();
        $gc = new GarbageCollector(
            $mapper,
            $logger,
            $limit,
            $thresholdDate,
            $this->perform(),
        );
        $gc = $gc
            ->addCheck(new Check\ForeignKeyRelation($db, $logger, SQL_db, $mapper->getTable()))
            ->addCheck(new Check\CharacterField($db, $logger))
            ->addCheck(new Check\TextField($db, $logger, SQL_db, $mapper->getTable()))
        ;

        $deleted = $gc->purge();

        // if nothing was deleted, consider that an error case?
        exit($deleted ? CliKernelAbstract::EXIT_STATUS_OK : CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
    }

    /**
     * This process must be run as `www-data`, because it may delete files from
     * the file system.
     *
     * @return string|null
     */
    public function getRequiredProcessUser(): ?string
    {
        return self::WEB_PROCESS_USER;
    }
}
