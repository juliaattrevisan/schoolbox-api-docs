<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Cli;

use Schoolbox\Application\CliTool;

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
     * Lock ttl (seconds)
     *
     * Reserves this amount for every iteration,
     * refreshing at the start of every iteration
     */
    private const LOCK_TTL = 60;


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


HELP;
            exit;
        }

        /* Check that Clipboard Module is enabled
         */
        if (!$this->getConfig()->get('clipboard_enabled')) {
            $this->getLogger()->info('Clipboard integration is not enabled; existing');
            exit();
        }

        /* Check that this isn't currently running
         */
        $lock = $this->acquireLock(self::CLIPBOARD_IMPORTER_LOCK_NAME, self::LOCK_TTL);

        if (!$lock->isAcquired()) {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
        }

        $this->getLogger()->info('Clipboard to Schoolbox Fixtures Hierarchy synchronisation.');

        try {
            $composites = (new \Schoolbox\Clipboard\Factory(
                $this->getLogger(),
                $this->perform(),
                $lock,
                (string)$this->getConfig()->get('clipboard_endpoint_host'),
                (string)$this->getConfig()->get('clipboard_endpoint_token')
            ))->produce(
                $this->getOpt(self::OPT_LONG_MEMBERSHIP),
                $this->getOpt(self::OPT_LONG_SESSIONS),
            );
        } catch (\DomainException $e) {
            $this->getLogger()->warning($e->getMessage());

            return;
        }

        foreach ($composites as $composite) {
            $composite->import();

            $this->getLogger()->debug(sprintf(
                'Refreshing lock for another %s seconds',
                self::LOCK_TTL
            ));

            $lock->refresh();
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
