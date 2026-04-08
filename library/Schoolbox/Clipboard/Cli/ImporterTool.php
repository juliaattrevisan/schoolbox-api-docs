<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Cli;

use Ramsey\Uuid\Uuid;
use Schoolbox\Application\CliTool;
use Schoolbox\Clipboard;

/**
 * Encapsulated cli tool
 */
class ImporterTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const CLIPBOARD_IMPORTER_LOCK_NAME = 'clipboard_importer';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --instance=...
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * --hierarchy
     *
     * Setting this flag imports Students and their relations in addition
     * to processing the Hierarchy
     */
    private const OPT_LONG_MEMBERSHIP = 'membership';


    /**
     * --sessions
     *
     * Setting this flag imports Clipboard Sessions, the equivalent of our Matchups,
     * assigning participating users and participating Positions
     */
    private const OPT_LONG_SESSIONS = 'sessions';


    /**
     * --partial
     *
     * Setting this flag enables Partial Update mode which lets you get changes
     * between two timestamps (automatically calculated) instead of replacing the
     * full population like it usually does
     */
    private const OPT_LONG_PARTIAL = 'partial';


    /**
     * Lock ttl (seconds)
     *
     * Reserves this amount for every iteration,
     * refreshing at the start of every iteration
     */
    private const LOCK_TTL = 120;

    /**
     * max execution time before we consider the import process timed out.
     */
    private const DEFAULT_TIMEOUT = 3600;

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_MEMBERSHIP,
            self::OPT_LONG_SESSIONS,
            self::OPT_LONG_PARTIAL
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import Clipboard Fixtures into schoolbox Fixtures, assuming command of our Fixtures
module in its entirety to do so. Schools will be sacrificing their Fixtures module
to support Clipboard imported data.

----

Import Clipboard Category/Subcategory/Teams into schoolbox Divisions/Teams equivalent.
This will not import students or their relationships or anything like that; it's the
barest bones that can be added.

Usage: php importer.php [--perform]

----

Import Clipboard Students into schoolbox Teams. This depends on the hierarchy which
you will end up obtaining anyway, so you have to augment with students' data by using this
additional flag. This would be your main day-to-day usage as it periodically synchronises
most of the Clipbard hierarchy to schoolbox.

Usage: php importer.php --membership [--perform]

---

Import Clipboard Sessions into schoolbox Matchups. This also depends on the existence of
 memberships. This creates schoolbox Fixtures, assigns students to their respective positions,
 adds coaches, etc. Anyone who can't be found will be skipped. This usage assigns team members
 to positions and matchups, so last-minute variations or alterations take place here.

Usage: php importer.php --sessions [--perform]

---

Partial Updates. You can import microchanges (updates, adds) by using the --partial flag.
This obtains changes between two timestamps: the last time the importer ran in --partial mode
and the current time.

TODO: Clipboard are still working on providing information about the nature of the updates
so we can facilitate deletions in similar manner.



HELP;
            exit;
        }

        /* Check that Clipboard Module is enabled
         */
        if (!$this->getConfig()->get('clipboard_enabled')) {
            $this->getLogger()->info('Clipboard integration is not enabled; exiting');
            exit;
        }

        /* Check that this isn't currently running
         */

        try {
            $lock = $this->acquireLock(self::CLIPBOARD_IMPORTER_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
        }

        $this->setMaxExecutionTime(self::DEFAULT_TIMEOUT);

        $this->getLogger()->info('Clipboard to Schoolbox Fixtures Hierarchy synchronisation.');

        /* Mappers
         */
        $importLogMapper = Clipboard\Log\Mapper::getInstance();

        /* phpstannis
         */
        $importLog = null;

        /* Each Clipboard Import gets its own run id
         */
        $runId = Uuid::uuid4()->toString();

        if ($this->getOpt(self::OPT_LONG_PARTIAL)) {
            $scriptName = Clipboard\Log\Model::PARTIAL_SCRIPT_NAME;
        } else {
            $scriptName = Clipboard\Log\Model::FULL_SCRIPT_NAME;
        }

        try {
            $composites = (new Clipboard\Factory(
                $this->getLogger(),
                $this->perform(),
                $lock,
                (string)$this->getConfig()->get('clipboard_endpoint_host'),
                (string)$this->getConfig()->get('clipboard_endpoint_token'),
                (string)$this->getConfig()->get('date_timezone')
            ))->produce(
                $this->getOpt(self::OPT_LONG_MEMBERSHIP),
                $this->getOpt(self::OPT_LONG_SESSIONS),
                $this->getOpt(self::OPT_LONG_PARTIAL),
            );
        } catch (\DomainException $e) {
            /* Specifically log when you couldn't produce the recipe, then leave
             * Anything could have caused that, so you might as well log it
             * then throw it back out
             */
            if ($this->perform()) {
                $importLog = $importLogMapper->start($runId, $scriptName);
                $importLogMapper->error($importLog, $e->getMessage());
            }

            $this->getLogger()->warning($e->getMessage());

            /* Releasing control
             */
            $lock->release();

            return;
        }

        /* Always log the start of the run
         */
        if ($this->perform()) {
            $importLog = $importLogMapper->start($runId, $scriptName);
        }

        try {
            foreach ($composites as $composite) {
                $this->getLogger()->debug(sprintf(
                    'Refreshing lock for another %s seconds',
                    self::LOCK_TTL
                ));

                $lock->refresh();

                $composite->import();
            }
        } catch (\Exception $e) {
            /* Any weird rando errors go here for now, instead of fatally crashing out
             */
            if ($this->perform()) {
                $importLogMapper->error($importLog, $e->getMessage());
            }

            $this->getLogger()->warning($e->getMessage());

            /* Releasing control
             */
            $lock->release();

            return;
        }

        if ($this->perform()) {
            /* Augment the Factory's recipe with the finisher
             *
             * This way you don't have to remember doing it in every factory;
             * you literally cannot forget to do it. It'll only log the finish
             * if you didn't crash somewhere in the process
             */
            $importLogMapper->finish($importLog);

            /* Trim away ancient logs
             */
            $importLogMapper->prune();
        }

        /* Only commit changes in Perform mode
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Releasing control
         */
        $lock->release();
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
}
