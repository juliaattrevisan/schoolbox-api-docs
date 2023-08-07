<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Factory;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Receiver;

/**
 * Takes a logged liss payload and replays it into your vagrant
 */
class Replay extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'liss_replay';


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
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_FILE = 'file:';


    /**
     * --source
     *
     * Optional source name; otherwise defaults to the Factory's const
     */
    private const OPT_LONG_SOURCE = 'source:';


    /**
     * Payloads state which endpoint they went to, but with a prefix
     * that we don't use.
     */
    private const LISS_METHOD_PREFIX = 'liss.';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE,
            self::OPT_LONG_SOURCE,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

LISS Payload Replay

Reloads a saved log file into your vagrant to replicate what schools would have
done with Edval/Timetabler pushes without needing their human to click the button.

You can specify a string to denote source of data which fulfils sis_event.source,
but it only works for the publishDailyData endpoint. Leaving it unset will cause
it to use the default value "suds_liss".

This is a diagnostic tool and always persists changes.

Usage: replay.php --file=... [--source=...]

Example: replay.php --file=./suds_liss_dailydata_20230803_155733.log --source=examplesource


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        $this->getLogger()->info('Commencing LISS Payload replay');

        /* See if the file exists
         */
        $filename = $this->getOpt(self::OPT_LONG_FILE);

        try {
            $fileExists = fopen($filename, 'r');
        } catch (\Exception $e) {
            $this->getLogger()->warning(sprintf(
                'Could not open file: %s',
                $filename
            ));

            $lock->release();
            return;
        }

        /* Once we can get the saved payload, try to reconstruct it
         * into something we can feed back into the Receiver
         */
        $mockMessage = unserialize(file_get_contents($filename), [
            'allowed_classes' => true
        ]);

        /* Instantiate liss server as usual and call the appropriate method directly
         */
        $superuser = \Schoolbox\UserMapper::getInstance()->findByUsername(\Schoolbox\UserMapper::USERNAME_ALARESS_ADMIN);

        $config = $this->getConfig();

        /* Decide on the source name, using the specified param if supplied
         * otherwise default to the Factory's const
         */
        $sourceName = trim((string)$this->getOpt(self::OPT_LONG_SOURCE));

        if ('' === $sourceName) {
            $sourceName = Factory::SOURCE_NAME;
        }

        $liss = new Receiver(
            (int)$config->get('external_enable'),
            $config->get('external_type'),
            $config->get('external_liss_username'),
            $config->get('external_liss_password'),
            $config->get('external_liss_exclusions'),
            $config->get('external_liss_default_event_type'),
            $config->get('external_liss_teacher_identifier'),
            (bool)$config->get('external_liss_storage_logging'),
            $config->get('logs_dir'),
            $sourceName,
            $superuser,
            \Schoolbox\External\Connector\Log\Mapper::getInstance(),
            \Schoolbox\Resource\Storage\Mapper::getInstance()
        );

        /* A blind guess about the endpoint originally intended
         */
        $method = str_replace(self::LISS_METHOD_PREFIX, '', $mockMessage->methodname);

        if (!method_exists($liss, $method)) {
            $this->getLogger()->warning(sprintf(
                'Unrecognised Receiver endpoint: %s',
                $method
            ));

            /* Announce lock release
             */
            $this->getLogger()->debug('Releasing lock');

            $lock->release();
        }

        /* Send it.
         */
        $this->getLogger()->info(sprintf(
            'Replaying Receiver method "%s" using source identifier "%s"',
            $method,
            $sourceName
        ));

        $liss->$method($mockMessage);
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
        return null;
    }
}
