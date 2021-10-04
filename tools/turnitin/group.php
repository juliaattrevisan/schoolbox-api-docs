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
use Schoolbox\Learning\Turnitin;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Turnitin\Api;
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
     * --instance=...
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * --force
     *
     * Optionally force retransmission of all group attachments in status Pending, Group Attachment, Uploading
     * since having a record sitting in this state for more than a few minutes is sign of a
     * stalled process. The Group Attachment and Uploading state isn't really meant to be experienced
     * by humans, so if you see it, something is going wrong.
     *
     * It also doesn't seem to hurt, doing these steps again. The outcome seems the same.
     * You could experience edge cases where a report will be in Group or Submission status
     * normally, but get reprocessed forcibly anyway.
     */
    private const OPT_LONG_FORCE_PUSH = 'force';


    /**
     * --group=...
     *
     * Choose a particular turnitin_group to affect
     */
    private const OPT_LONG_GROUP_ID = 'group:';


    /**
     * Lock name
     */
    private const TURNITIN_LOCK_NAME = 'turnitin_group';


    /**
     * Lock ttl
     */
    private const LOCK_TTL = 10 * 60;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_GROUP_ID,
            self::OPT_LONG_FORCE_PUSH
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find every Pending turnitin_group record and upload the associated files' binaries
to Turnitin. Turnitin Group Attachments are synonymous with our Schoolbox Submission
Box Files (the attachments to Due Works)

Transmission of Turnitin Group Attachments goes through these stages, with their 
status strings:

1. Saved in schoolbox db with status Pending             [pending]
2. Set up a Turnitin Group if one doesn't already exist  [group]
3. Set up Turnitin Group Attachment(s)                   [group_attachment]
4. Upload file contents into Turnitin Group Attachment   [uploading]
5. Contents finished sending to Turnitin                 [complete]

You can optionally specify a group id to just affect that record alone, in case you need
to fix a single record but not any others. The group id is the primary key of turnitin_group 
and its status must be "pending", "group" or "group_attachment" for it to be found.

You can optionally force retransmission of Pending, Group or Group Attachment statuses by using
the --force flag. Anything in those statuses will be forcibly re-sent to turnitin.

Usage: group.php [--instance=INSTANCE_ID] [--group=1234] [--force]


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::TURNITIN_LOCK_NAME, self::LOCK_TTL);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            /* Find all the records to process in a synonymous data struct
             */
            if ($this->getOpt(self::OPT_LONG_GROUP_ID)) {
                /* Optionally:
                 * Forcibly retransmit everything in Pending, Group Attachment, Uploading
                 * regardless of whether they're in those states normally; used
                 * to recommence a stalled or broken process, but you might also
                 * reprocess something that's in this state naturally
                 */
                if ($this->getOpt(self::OPT_LONG_FORCE_PUSH)) {
                    $models = Turnitin\Group\Mapper::getInstance()->findByColumnList([
                        'id' => $this->getOpt(self::OPT_LONG_GROUP_ID),
                        'status' => [
                            Turnitin\Group\Model::STATUS_TRANSMIT_PENDING,
                            Turnitin\Group\Model::STATUS_TRANSMIT_GROUP,
                            Turnitin\Group\Model::STATUS_TRANSMIT_GROUP_ATTACHMENT,
                        ]
                    ]);
                } else {
                    $models = Turnitin\Group\Mapper::getInstance()->findByColumnList([
                        'id' => $this->getOpt(self::OPT_LONG_GROUP_ID),
                        'status' => Turnitin\Group\Model::STATUS_TRANSMIT_PENDING
                    ]);
                }
            } else {
                /* Optionally:
                 * Forcibly retransmit everything in Pending, Group, Group Attachment
                 * regardless of whether they're in those states normally; used
                 * to recommence a stalled or broken process, but you might also
                 * reprocess something that's in this state naturally
                 */
                if ($this->getOpt(self::OPT_LONG_FORCE_PUSH)) {
                    $models = Turnitin\Group\Mapper::getInstance()->findByColumn('status', [
                        Turnitin\Group\Model::STATUS_TRANSMIT_PENDING,
                        Turnitin\Group\Model::STATUS_TRANSMIT_GROUP,
                        Turnitin\Group\Model::STATUS_TRANSMIT_GROUP_ATTACHMENT,
                    ]);
                } else {
                    $models = Turnitin\Group\Mapper::getInstance()->findByColumn('status', Turnitin\Group\Model::STATUS_TRANSMIT_PENDING);
                }
            }

            /* Leave immediately when nothing to do
             */
            if (!$models->count()) {
                $this->getLogger()->info('There are no pending Turnitin groups; nothing to do');
                exit();
            }

            /* Otherwise keep going
             */
            $this->getLogger()->info(sprintf(
                'Commencing upload sequence for %s pending Turnitin groups',
                $models->count()
            ));

            /* We need this api
             */
            $api = new Api(
                $this->getConfig()->get('site_version'),
                $this->getConfig()->get('turnitin_api_host'),
                $this->getConfig()->get('turnitin_api_key')
            );

            /* Set up a record processor
             */
            $processor = (new Turnitin\Group\Process\Group(
                $this->getConfig()->get('site_type'),
                $api,
                Turnitin\Group\Mapper::getInstance(),
                Turnitin\Group\Attachment\Mapper::getInstance(),
                SubmissionBoxMapper::getInstance(),
                SubmissionBox\SingleFile\Mapper::getInstance(),
                $this->getLogger()
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
