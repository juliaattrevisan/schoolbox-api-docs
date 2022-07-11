<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Helper;
use Schoolbox\UserMapper;

/**
 * CLI Tool for processing Turnitin Files and their uploads
 */
class TransmitTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_transmit';


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
     * Optionally force retransmission of all reports in status Pending, Group, Submission
     * since having a report sitting in this state for more than a few minutes is sign of a
     * stalled process. The Group and Submission states aren't really meant to be experienced
     * by humans, so if you see it, something is going wrong.
     *
     * It also doesn't seem to hurt, doing these steps again. The outcome seems the same.
     * You could experience edge cases where a report will be in Group or Submission status
     * normally, but get reprocessed forcibly anyway.
     *
     * Example scenario:
     * A network timeout will cause a process to halt in one of these states, and there won't
     * be an error message. You just have to know that you need to retransmit it, but you
     * can't distinguish this from another report that is in that state naturally and normally.
     * This can happen when new reports are made at the time a cron transmit.php happens to run.
     * This would be a problem regardless of how many status changes you recorded.
     *
     * Example scenario:
     * The backup to turnitin webhooks, a cron task, always grabs any laggards and forcibly
     * sends them up, assuming anything in Pending, Group, Submission status should really be
     * pushed onwards immediately.
     */
    private const OPT_LONG_FORCE_PUSH = 'force';


    /**
     * --report=...
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
            self::OPT_LONG_REPORT_ID,
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

Find every Pending turnitin report in turnitin_report and set up the Turnitin Group, Submission
and upload the file's contents. Used periodically to kick everything up to Turnitin for another
async process to do its work.

Transmission of Turnitin files goes through these stages, with their status strings:

1. Saved in schoolbox db with status Pending                                   [pending]
2. Set up Turnitin Group, analogous to our submission boxes                    [group]
3. Set up Turnitin Submission, analogous to our student submission files       [submission]
3a. Accept EULA on behalf of student; no status change, it's kind of implicit  -
4. Upload file contents into Turnitin Submission                               [uploading]
5. Contents finished sending to Turnitin for further processing                [loaded]

When you get to stage 5, another async process takes over to retrieve the report itself.

You can optionally specify a report id to just affect that report alone, in case you need
to fix a single report but not any others. The report id is the primary key of turnitin_report
and its report status must be "pending" for it to be found.

You can optionally force retransmission of Pending, Group and Submission statuses by using
the --force flag. Anything in those statuses will be forcibly re-sent to Turnitin.

Usage: transmit.php [--instance=INSTANCE_ID] [--report=1234] [--force]

----

(For those ancient support kings, Turnitin "transmit.php" is analogous to plag "submit.php")


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
            /* Find all the records to process in a synonymous data struct
             */
            if ($this->getOpt(self::OPT_LONG_REPORT_ID)) {
                /* Optionally:
                 * Forcibly retransmit everything in Pending, Group, Submission
                 * statuses - regardless of whether they're in those states normally; used
                 * to recommence a stalled or broken process, but you might also
                 * reprocess something that's in this state naturally
                 */
                if ($this->getOpt(self::OPT_LONG_FORCE_PUSH)) {
                    $models = Turnitin\File\Mapper::getInstance()->findByColumnList([
                        'id' => $this->getOpt(self::OPT_LONG_REPORT_ID),
                        'status' => [
                            Turnitin\File\Model::STATUS_TRANSMIT_PENDING,
                            Turnitin\File\Model::STATUS_TRANSMIT_GROUP,
                            Turnitin\File\Model::STATUS_TRANSMIT_SUBMISSION,
                        ]
                    ]);
                } else {
                    $models = Turnitin\File\Mapper::getInstance()->findByColumnList([
                        'id' => $this->getOpt(self::OPT_LONG_REPORT_ID),
                        'status' => Turnitin\File\Model::STATUS_TRANSMIT_PENDING
                    ]);
                }
            } else {
                /* Optionally:
                 * Forcibly retransmit everything in Pending, Group, Submission
                 * regardless of whether they're in those states normally; used
                 * to recommence a stalled or broken process, but you might also
                 * reprocess something that's in this state naturally
                 */
                if ($this->getOpt(self::OPT_LONG_FORCE_PUSH)) {
                    $models = Turnitin\File\Mapper::getInstance()->findByColumn('status', [
                        Turnitin\File\Model::STATUS_TRANSMIT_PENDING,
                        Turnitin\File\Model::STATUS_TRANSMIT_GROUP,
                        Turnitin\File\Model::STATUS_TRANSMIT_SUBMISSION,
                    ]);
                } else {
                    $models = Turnitin\File\Mapper::getInstance()->findByColumn('status', Turnitin\File\Model::STATUS_TRANSMIT_PENDING);
                }
            }

            /* Leave immediately when nothing to do
             */
            if (!$models->count()) {
                $this->getLogger()->info('There are no pending Turnitin reports; nothing to do');
                $this->getLogger()->info('Done.');
                exit();
            }

            /* Otherwise keep going
             */
            $this->getLogger()->info(sprintf(
                'Commencing upload sequence for %s pending Turnitin reports',
                $models->count()
            ));

            /* We need this api
             */
            $api = new Turnitin\Api(
                $this->getConfig()->get('site_version'),
                $this->getConfig()->get('turnitin_api_host'),
                $this->getConfig()->get('turnitin_api_key')
            );

            /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy */
            $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');

            /* Set up a record processor
             */
            $processor = (new Turnitin\File\Process\Transmit(
                $this->getConfig()->get('site_type'),
                $api,
                Turnitin\File\Mapper::getInstance(),
                Turnitin\Group\Mapper::getInstance(),
                UserMapper::getInstance(),
                Component\SubmissionBoxMapper::getInstance(),
                Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(),
                $assessmentMapperProxy,
                $this->getLogger()
            ));

            /* Shank them all up
             */
            foreach ($models as $model) {
                $this->getLogger()->debug(sprintf(
                    'Refreshing lock for another %s seconds',
                    self::LOCK_TTL
                ));

                $lock->refresh();

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
}
