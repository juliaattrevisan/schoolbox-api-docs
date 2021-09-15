#!/usr/bin/php
<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Lock\InstanceLockFactory;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;


require_once __DIR__ . '/../../conf/core/require.php';

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
     * --instance
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * Lock name
     */
    private const TRANSMIT_LOCK_NAME = 'turnitin_transmit';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find every Pending turnitin report in turnitin_report and set up the turnitin Group, Submission
and upload the file's contents. Used periodically to kick everything up to turnitin for another
async process to do its work.

Transmission of turnitin files goes through these stages, with their status strings:

1. Saved in schoolbox db with status Pending ("pending")
2. Set up Turnitin Group, analogous to our submission boxes ("group")
3. Set up Turnitin Submission, analagous to our student submission files ("submission")
3a. Accept EULA on behalf of student; no status for this step, it's kind of implicit
4. Upload file contents into Turnitin Submission ("uploading")
5. Contents finished sending to Turnitin for further processing ("loaded")

When you get to stage 5, another async process takes over to retrieve the report itself.

----

(For those ancient support kings, Turnitin "transmit.php" is analogous to plag "submit.php")

Usage: transmit.php --instance=INSTANCE_ID


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::TRANSMIT_LOCK_NAME, 10*60);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            /* We need this api
             */
            $api = new Turnitin\Api(
                $this->getConfig()->get('site_version'),
                $this->getConfig()->get('turnitin_api_host'),
                $this->getConfig()->get('turnitin_api_key')
            );

            /* Set up a record processor
             */
            $processor = (new Turnitin\Process\File(
                $api,
                Turnitin\Mapper::getInstance(),
                UserMapper::getInstance(),
                Component\SubmissionBoxMapper::getInstance(),
                SingleFile\Mapper::getInstance(),
                $this->getLogger()
            ));

            /* Find all the Pending records
             */
            $models = Turnitin\Mapper::getInstance()->findByColumn('status', Turnitin\Model::STATUS_SETUP_PENDING);

            $this->getLogger()->info(sprintf(
                'Commencing upload sequence for %s pending Turnitin reports.',
                $models->count()
            ));

            /* Shank them all up
             */
            foreach ($models as $model) {
                $processor->process($model);
            }

            $this->getLogger()->info('Done.');

            /* Releasing control
             */
            $lock->release();
        } else {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
