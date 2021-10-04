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
     * --instance
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * --report
     *
     * Choose a particular turnitin_report to affect
     */
    private const OPT_LONG_REPORT_ID = 'report:';


    /**
     * Lock name
     */
    private const TURNITIN_LOCK_NAME = 'turnitin_report';


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
            self::OPT_LONG_REPORT_ID
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find every Loaded turnitin report in turnitin_report and try to get the similarity score.
This tool can be used in conjunction with transmit.php to manually complete the turnitin
process, in case their webhooks fail or if you just want to do it by hand.

1. An upload finished being sent to turnitin; you start here ("loaded")
2. Turnitin finishes processing the upload and is ready for report to be scheduled ("prepared")
3. Report scheduling succeeds and you're now waiting for report to generate ("analysing")
4. Report finished and we cached some report data locally ("complete")

You can optionally specify a report id to just affect that report alone, in case you need
to fix a single report but not any others. The report id is the primary key of turnitin_report
and its report status must be one of "loaded", "prepared" or "analysing" for it to be found.

Usage: report.php [--instance=INSTANCE_ID] [--report=1234]

----

(For those ancient support kings, Turnitin "report.php" is analogous to plag "scan.php")


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
            /* We need this api
             */
            $api = new Api(
                $this->getConfig()->get('site_version'),
                $this->getConfig()->get('turnitin_api_host'),
                $this->getConfig()->get('turnitin_api_key')
            );

            /* Set up a record processor
             */
            $processor = (new Turnitin\File\Process\Report(
                $api,
                Turnitin\File\Mapper::getInstance(),
                $this->getLogger()
            ));

            /* Start kicking things along
             *
             * Loaded reports get shoved toward Prepared
             * Prepared reports get shoved toward Complete
             *
             * Both those stages finish whenever turnitin is done
             * so we have to ask it to finish both stages independently
             * of each other
             */
            $this->processLoadedReports($processor);
            $this->processPreparedReports($processor);

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
     * Find all the reports that were loaded and move them into Prepared, meaning
     * we have told turnitin to schedule a report generation
     *
     * @param Turnitin\File\Process\Report $processor
     */
    private function processLoadedReports(Turnitin\File\Process\Report $processor): void
    {
        /* Find all the records to process in a synonymous data struct
         */
        if ($this->getOpt(self::OPT_LONG_REPORT_ID)) {
            $models = Turnitin\File\Mapper::getInstance()->findByColumnList([
                'id' => $this->getOpt(self::OPT_LONG_REPORT_ID),
                'status' => Turnitin\File\Model::STATUS_TRANSMIT_LOADED
            ]);
        } else {
            $models = Turnitin\File\Mapper::getInstance()->findByColumn('status', Turnitin\File\Model::STATUS_TRANSMIT_LOADED);
        }

        /* Leave immediately when nothing to do
         */
        if (!$models->count()) {
            $this->getLogger()->info('There are no loaded Turnitin reports; nothing to do');
            return;
        }

        /* Otherwise keep going
         */
        $this->getLogger()->info(sprintf(
            'Asking Turnitin for %s loaded Turnitin reports',
            $models->count()
        ));

        /* Shank them all down
         */
        foreach ($models as $model) {
            $processor->processLoadedReports($model);
        }
    }


    /**
     * Find all the reports that are currently in Prepared (meaning they're ready to
     * be generated) and see when they reach Complete, at which point we remember
     * the answers
     *
     * @param Turnitin\File\Process\Report $processor
     */
    private function processPreparedReports(Turnitin\File\Process\Report $processor): void
    {
        /* Find all the records to process in a synonymous data struct
         */
        if ($this->getOpt(self::OPT_LONG_REPORT_ID)) {
            $models = Turnitin\File\Mapper::getInstance()->findByColumnList([
                'id' => $this->getOpt(self::OPT_LONG_REPORT_ID),
                'status' => [
                    Turnitin\File\Model::STATUS_REPORT_PREPARED,
                    Turnitin\File\Model::STATUS_REPORT_ANALYSING
                ]
            ]);
        } else {
            $models = Turnitin\File\Mapper::getInstance()->findByColumn('status', [
                Turnitin\File\Model::STATUS_REPORT_PREPARED,
                Turnitin\File\Model::STATUS_REPORT_ANALYSING
            ]);
        }

        /* Leave immediately when nothing to do
         */
        if (!$models->count()) {
            $this->getLogger()->info('There are no prepared Turnitin reports; nothing to do');
            return;
        }

        /* Otherwise keep going
         */
        $this->getLogger()->info(sprintf(
            'Asking Turnitin for %s prepared Turnitin reports',
            $models->count()
        ));

        /* Shank them all down
         */
        foreach ($models as $model) {
            $processor->processPreparedReports($model);
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
