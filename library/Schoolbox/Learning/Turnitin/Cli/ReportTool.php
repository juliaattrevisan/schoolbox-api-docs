<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Helper;
use Symfony\Component\Lock\LockInterface;

/**
 * CLI Tool for generating Turnitin Reports
 */
class ReportTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_report';


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

Find every Loaded Turnitin report in turnitin_report and try to get the similarity score.
This tool can be used in conjunction with transmit.php to manually complete the Turnitin
process, in case their webhooks fail or if you just want to do it by hand.

1. An upload finished being sent to Turnitin; you start here                        [loaded]
2. Turnitin finishes processing the upload and is ready for report to be scheduled  [prepared]
3. Report scheduling succeeds and you're now waiting for report to generate         [analysing]
4. Report finished and we cached some report data locally                           [complete]

You can optionally specify a report id to just affect that report alone, in case you need
to fix a single report but not any others. The report id is the primary key of turnitin_report
and its report status must be one of "loaded", "prepared" or "analysing" for it to be found.

Usage: report.php [--instance=INSTANCE_ID] [--report=1234]

----

Historical turnitin documents are also processed with this tool. They follow the same algorithm
and basic steps; the main difference is that instead of generating a report, we just put the
file's contents into turnitin's brain and leave it at that. To represent this step, we have
a special status "indexing". Everything else remains more-or-less synonymous so you don't have
to think too hard about this.

1. An upload finished being sent to Turnitin; you start here                        [loaded]
2. Turnitin finishes processing the upload and is ready for indexing                [prepared]
3. Indexing the document's contents begins                                          [indexing]
4. Indexing finished and there is nothing more to do                                [complete]

There is no fundamental difference between historical documents and regular contemporary ones.
Historical documents are simply processed in a different way.

----

(For those ancient support kings, Turnitin "report.php" is analogous to plag "scan.php")


HELP;
            exit;
        }

        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfig()))->isConfigured()) {
            $this->getLogger()->info('Turnitin configuration invalid.');
            exit;
        }

        /* Check that this isn't currently running
         */
        try {
            $lock = $this->acquireLock(self::TURNITIN_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $lock = null;
        }

        if ($lock) {
            /* We need this api
             */
            $api = new Turnitin\Api(
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
            $this->processLoadedReports($processor, $lock);
            $this->processPreparedReports($processor, $lock);

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
     * @param LockInterface                $lock
     */
    private function processLoadedReports(Turnitin\File\Process\Report $processor, LockInterface $lock): void
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
            $this->getLogger()->debug(sprintf(
                'Refreshing lock for another %s seconds',
                self::LOCK_TTL
            ));

            $lock->refresh();

            $processor->processLoadedReports($model);
        }
    }


    /**
     * Find all the reports that are currently in Prepared (meaning they're ready to
     * be generated) and see when they reach Complete, at which point we remember
     * the answers
     *
     * @param Turnitin\File\Process\Report $processor
     * @param LockInterface                $lock
     */
    private function processPreparedReports(Turnitin\File\Process\Report $processor, LockInterface $lock): void
    {
        /* Find all the records to process in a synonymous data struct
         */
        if ($this->getOpt(self::OPT_LONG_REPORT_ID)) {
            $models = Turnitin\File\Mapper::getInstance()->findByColumnList([
                'id' => $this->getOpt(self::OPT_LONG_REPORT_ID),
                'status' => [
                    Turnitin\File\Model::STATUS_REPORT_PREPARED,
                    Turnitin\File\Model::STATUS_REPORT_ANALYSING,
                    Turnitin\File\Model::STATUS_REPORT_INDEXING
                ]
            ]);
        } else {
            $models = Turnitin\File\Mapper::getInstance()->findByColumn('status', [
                Turnitin\File\Model::STATUS_REPORT_PREPARED,
                Turnitin\File\Model::STATUS_REPORT_ANALYSING,
                Turnitin\File\Model::STATUS_REPORT_INDEXING
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
            $this->getLogger()->debug(sprintf(
                'Refreshing lock for another %s seconds',
                self::LOCK_TTL
            ));

            $lock->refresh();

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
}
