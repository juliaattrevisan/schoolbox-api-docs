<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC;

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
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
     * Default garbage collection threshold.
     */
    private const DEFAULT_THRESHOLD = '2 months ago';

    /**
     * --batch={batch-size}: process this many records per batch.
     */
    private const OPT_LONG_BATCH_SIZE = 'batch:';

    /**
     * Default batch size.
     */
    private const DEFAULT_BATCH_SIZE = 10;

    /**
     * --max-time={max-time}: run for this many seconds before exiting.
     */
    private const OPT_LONG_MAX_TIME = 'max-time:';

    /**
     * Default max time: if not set or <= 0, no max time is set.
     */
    private const DEFAULT_MAX_TIME = null;

    /**
     * --force-run-on-staging: unless this flag is provided, do not allow this
     * tool to run on staging instances.
     */
    private const OPT_LONG_FORCE_RUN_ON_STAGING = 'force-run-on-staging';

    /**
     * --force-run-if-disabled: unless this flag is provided, do not allow this
     * tool to run if config option `scheduled_storage_cleanup_enabled` is off.
     */
    private const OPT_LONG_FORCE_RUN_IF_DISABLED = 'force-run-if-disabled';

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
            self::OPT_LONG_BATCH_SIZE,
            self::OPT_LONG_MAX_TIME,
            self::OPT_LONG_FORCE_RUN_ON_STAGING,
            self::OPT_LONG_FORCE_RUN_IF_DISABLED,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
NAME
    gc-storage.php - cleans up unused files uploaded to Schoolbox storage

SYNOPSIS
    gc-storage.php [OPTION]...

DESCRIPTION
    This tool detects whether an uploaded file is still in use in any place within Schoolbox, and if it is not will
    delete the file completely.

    A file is determined to be in use if:
    * it is directly referenced in any foreign key relation onto the `storage` table
    * its hash value is indirectly referenced in a URL or HTML content in any table

    The tool will delete:
    * the file's reference in the `storage` table
    * the file itself from local disk or cloud-based object storage
    * any image transforms or audio/video transcodes for those deleted files

OPTIONS
    -h, --help:              Print this help text and exit
    -p:                      perform: *without* this flag, this tool will only do a dry run, outputting what would be
                             deleted
    -v[v[v]]:                verbosity: how much activity will be logged
    -n={limit}:              the maximum number of storage records to check.
                             Defaults to 1000: if <= 0, do not limit number of records.
    --max-time={max-time}:   process hashes in batches until the script has been running for this many seconds.
                             Defaults to no max time: if <= 0, do not limit the time the script runs for.
    --batch={batch-size}:    process this many hashes per batch: defaults to 10.
    -t={threshold}:          threshold: do not attempt to clean up storage records last checked more recently than this
                             date threshold, defaults to 2 months ago.
                             If provided, must be supplied as one of:
                             * a "YYYY-MM-DD HH:MM:SS" date
                             * a timestamp
                             * a relative date value understandable by `strtotime`
    --force-run-if-disabled: unless this flag is provided, this tool will not run if config option
                             `scheduled_storage_cleanup_enabled` (Enable Scheduled Storage Cleanup) is off.
    --force-run-on-staging:  unless this flag is provided, this tool will not run on a staging instance.
                             Since staging and production instances will likely share file storage, deleting a file from
                             staging will delete it from production as well.
                             If this flag is provided, the run must be further confirmed by answering a prompt.

HELP;
            exit;
        }

        $logger = $this->getLogger();

        // Keep running or exit, depending on whether it's enabled in config.
        if (!$this->getConfig()->get(ConfigMapper::NAME_SCHEDULED_STORAGE_CLEANUP_ENABLED)) {
            if ($this->getOpt(self::OPT_LONG_FORCE_RUN_IF_DISABLED)) {
                $logger->notice(
                    sprintf(
                        'Config option `%s` is off, but --%s flag is provided: running storage cleanup',
                        ConfigMapper::NAME_SCHEDULED_STORAGE_CLEANUP_ENABLED,
                        self::OPT_LONG_FORCE_RUN_IF_DISABLED,
                    ),
                );
            } else {
                $logger->notice(
                    sprintf(
                        'Config option `%s` is off: not running storage cleanup.',
                        ConfigMapper::NAME_SCHEDULED_STORAGE_CLEANUP_ENABLED,
                    ),
                );
                $logger->notice(
                    sprintf(
                        'To run the storage cleanup, run again with the --%s flag.',
                        self::OPT_LONG_FORCE_RUN_IF_DISABLED,
                    ),
                );
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }
        }

        $mapper = StorageMapper::getInstance();
        if ($this->perform()) {
            /** @var Instance $instance */
            $instance = $this->getDi()->get('instance');
            if ($instance->isStaging()) {
                $forceRunOnStaging = $this->getOpt(self::OPT_LONG_FORCE_RUN_ON_STAGING);
                if ($forceRunOnStaging) {
                    $logger->warning('Running in perform mode on a staging instance: this could delete data on the production instance! Choose an option:');
                    $logger->warning('y: delete from database and filesystem, possibly affecting production');
                    $logger->warning('n: delete from database, do not delete from filesystem');
                    $logger->warning('q: exit');
                    while (true) {
                        $continue = strtolower($this->readLine('Allow deletion? [y/n/q]: '));
                        if ($continue === 'y') {
                            $logger->warning('Deleting filesystem data on staging instance, possibly affecting production');
                            $mapper->setAllowStagingDeleteStorage(true);
                            break;
                        }
                        if ($continue === 'n') {
                            $logger->notice('Not deleting filesystem data on staging instance');
                            break;
                        }
                        if ($continue === 'q') {
                            exit(CliKernelAbstract::EXIT_STATUS_OK);
                        }
                    }
                } else {
                    $logger->notice('Refusing to run in perform mode on a staging instance without the --force-run-on-staging flag.');
                    exit(CliKernelAbstract::EXIT_STATUS_OK);
                }
            } else {
                $logger->notice('Running in perform mode: this will permanently delete files.');
            }
        } else {
            $logger->notice('Running in dry-run mode: use -p flag to permanently delete files.');
        }

        $batchSize = $this->getOptAsInt(self::OPT_LONG_BATCH_SIZE, self::DEFAULT_BATCH_SIZE);
        if ($batchSize <= 0) {
            $batchSize = self::DEFAULT_BATCH_SIZE;
        }
        $limit = $this->getOptAsInt(self::OPT_SHORT_LIMIT, self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = null;
        }
        $maxTime = $this->getOptAsInt(self::OPT_LONG_MAX_TIME, self::DEFAULT_MAX_TIME);
        if ($maxTime <= 0) {
            $maxTime = null;
        }

        $threshold     = $this->getOpt(self::OPT_SHORT_THRESHOLD);
        $thresholdDate = $this->buildDate($threshold ?? self::DEFAULT_THRESHOLD);
        $db            = $this->getDatabaseInternal();
        $gc = new GarbageCollector(
            $mapper,
            $logger,
            $this->perform(),
            $batchSize,
            limit: $limit,
            maxTime: $maxTime,
            thresholdDate: $thresholdDate,
        );
        $gc = $gc
            ->addCheck(new Check\ForeignKeyRelation($db, $logger, SQL_db, $mapper->getTable()))
            ->addCheck(new Check\CharacterField($db, $logger, SQL_db))
            ->addCheck(new Check\TextField($db, $logger, SQL_db, $mapper->getTable()))
        ;

        $gc->purge();
        exit(CliKernelAbstract::EXIT_STATUS_OK);
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
