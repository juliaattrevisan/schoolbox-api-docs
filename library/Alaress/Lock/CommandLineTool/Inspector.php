<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Lock\CommandLineTool;

use Alaress\Image\Processor\Manager;
use Alaress\Lock\InstanceLockFactory;
use External\Canvas\Importer\Cli\AnnouncementsTool;
use Predis\ClientInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Calendar\Cli\TrimmerTool;
use Schoolbox\Clipboard\Cli\ImporterTool;
use Schoolbox\External\Importer\Endpoint\Scheduler;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\ErrorRequeuer;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\StatusChecker;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\Uploader;
use Schoolbox\Learning\Turnitin\Cli\CleanserTool;
use Schoolbox\Learning\Turnitin\Cli\TransmitTool;
use Schoolbox\Learning\Turnitin\Cli\ReportTool;
use Schoolbox\Learning\Turnitin\Cli\GroupTool;
use Schoolbox\Learning\Turnitin\Cli\RetrofitTool;
use Schoolbox\Learning\Turnitin\Cli\HistoricalTool;
use Schoolbox\Pastoral\CommandLineTool\AttachmentUploader;
use Schoolbox\Resource\Storage\Cli\ExpiryTool;
use Schoolbox\Video\CommandLineTool\Processor;
use Schoolbox\Intellischool\CommandLineTool\DataSync;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * Inspects the status of all locks within Schoolbox, and optionally allows them
 * to be forcibly unlocked.
 */
class Inspector extends CliTool\App
{
    // -y: answer "Y" to all prompts
    private const OPT_SHORT_ANSWER_YES = 'y';

    // -n: answer "N" to all prompts, the default if not interactive
    private const OPT_SHORT_ANSWER_NO = 'n';

    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    // --instance: override instance ID
    private const OPT_LONG_INSTANCE = 'instance:';

    // --key: only report a particular lock
    private const OPT_LONG_INSPECT = 'inspect:';

    // --unlock: only unlock a particular lock
    private const OPT_LONG_UNLOCK = 'unlock:';

    private const LOCK_NAMES = [
        ErrorRequeuer::LOCK_NAME      => 'Kami Error Requeuer (./tools/kami/requeue-errors.php)',
        StatusChecker::LOCK_NAME      => 'Kami Status Checker (./tools/kami/check-status.php)',
        Uploader::LOCK_NAME           => 'Kami Uploader (./tools/kami/upload.php)',
        AttachmentUploader::LOCK_NAME => 'Pastoral Attachment Uploader (./tools/docman/pastoral-upload.php)',
        Processor::LOCK_NAME          => 'Video Processor (./tools/video/processor.php)',
        Manager::LOCK_NAME            => 'Image Processor (./tools/image/processor.php)',
        Scheduler::LOCK_NAME          => 'SUDS On-Demand Importer (./tools/synchronise/sisImporter.php) and SUDS Automated Scheduler (./tools/synchronise/scheduler.php)',
        'transform_cleanup'           => 'Storage transcode cleanup',

        /* Turnitin CLI Tools
         */
        GroupTool::TURNITIN_LOCK_NAME    => 'Turnitin Group Attachment (./tools/turnitin/group.php)',
        TransmitTool::TURNITIN_LOCK_NAME => 'Turnitin File Transmitter (./tools/turnitin/transmit.php)',
        ReportTool::TURNITIN_LOCK_NAME   => 'Turnitin Report Generator (./tools/turnitin/report.php)',
        RetrofitTool::TURNITIN_LOCK_NAME => 'Turnitin File Retrofitter (./tools/turnitin/retrofit.php)',
        CleanserTool::TURNITIN_LOCK_NAME => 'Turnitin Group Cleanser (./tools/turnitin/cleanser.php)',
        HistoricalTool::TURNITIN_LOCK_NAME => 'Turnitin Historical (./tools/turnitin/historical.php)',

        //Intellischool CLI tool
        DataSync::LOCK_NAME      =>  'Intellischool Sync Handler (./tools/intellischool/dataSync.php)',

        /* Canvas Announcements Importer
         */
        AnnouncementsTool::CANVAS_ANNOUNCEMENTS_LOCK_NAME => 'Canvas Announcements Importer (./tools/canvas/announcements.php)',

        /* Clipboard CLI Tools
         */
        ImporterTool::CLIPBOARD_IMPORTER_LOCK_NAME => 'Clipboard Sport/Division/Team/Players/Sessions (./tools/clipboard/importer.php)',

        /* Expired Storage Removal Tool
         */
        ExpiryTool::EXPIRED_STORAGE_REMOVAL_LOCK_NAME => 'Expired Storage Removal Tool (./tools/storage/expired-storage-removal.php)',

        /* Dead Event Trimmer
         */
        TrimmerTool::LOCK_NAME => 'Dead Event Trimmer Tool (./tools/calendar/trimmer.php)',
    ];


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP, self::OPT_SHORT_ANSWER_YES, self::OPT_SHORT_ANSWER_NO,];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_INSTANCE, self::OPT_LONG_INSPECT, self::OPT_LONG_UNLOCK];
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file   = $this->getToolFilename();
        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        // Output lock description as well
        $locks = [];
        foreach (self::LOCK_NAMES as $lockName => $lockDescription) {
            $locks[] = $yellow . $lockName . $white;
            $locks[] = '    ' . $lockDescription;
        }
        $locks = implode("\n        ", $locks);

        $help = "$white

NAME:
    Lock inspector

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Tool for inspecting and breaking process locks.

    By default, the inspector will prompt whether to break a lock if the session
    is interactive: otherwise, it will simply report the status of locks.
    Use the `$yellow-y$white` or `$yellow-n$white` options to override this behavior.

    $yellow-h, --help$white
        Print this help and exit

    $yellow-y$white
        Answer Y to all prompts to break a lock.

    $yellow-n$white
        Answer N to all prompts to break a lock: the default behavior if the
        session is not interactive.
        If explicitly provided, this option conflicts with `$yellow--unlock$white`.

    $yellow--instance=INSTANCE_ID$white
        If provided, inspect locks for the given instance.

    $yellow--inspect=KEY$white
        If provided, only inspect the given lock. The following locks are
        available:
        $locks

        This option may be provided more than once to inspect multiple locks.

    $yellow--unlock=KEY$white
        If provided, only unlock the given lock. This is the equivalent of
        `$yellow--inspect$white` with the `$yellow-y$white` option.
        This option may be provided more than once to unlock multiple locks.
        This option conflicts with `$yellow-n$white` if that option is explicitly provided.
";
        \fwrite(STDOUT, $help);
    }


    /**
     * @inheritDoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }


        // You really want to bypass symfony-lock to get at the juicy Redis
        // goodness within? Okay then
        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis.cache');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;
        $this->getLogger()->notice(sprintf('Running for instance "%s"', $instanceId));

        // Verify no conflict between options
        $unlockFilter     = (array)$this->getOpt(self::OPT_LONG_UNLOCK);
        $forceInspectOnly = (bool)$this->getOpt(self::OPT_SHORT_ANSWER_NO);
        if (!empty($unlockFilter) && $forceInspectOnly) {
            $this->getLogger()->error('`--unlock` option cannot be used with `-n`: exiting');
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        // Verify lock names are correct
        $inspectFilter = (array)$this->getOpt(self::OPT_LONG_INSPECT);
        $searchFilter  = array_merge($inspectFilter, $unlockFilter);
        if (!empty($searchFilter)) {
            foreach ($searchFilter as $lockName) {
                if (!isset(self::LOCK_NAMES[$lockName])) {
                    $this->getLogger()->warning(
                        sprintf(
                            'Lock name "%s" provided in `--inspect` or `--unlock` option is not recognised: ignoring',
                            $lockName,
                        )
                    );
                }
            }
        }

        // otherwise, report on locks
        foreach (self::LOCK_NAMES as $lockName => $lockDescription) {
            if (!empty($searchFilter) && !in_array($lockName, $searchFilter)) {
                continue;
            }
            $this->getLogger()->notice(sprintf('Testing lock: %s, script: %s', $lockName, $lockDescription));

            $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
            $lock        = $lockFactory->createLock($lockName, 0);
            $key         = $lockFactory->calculateKeyName($lockName);

            // Is this lock acquirable?
            $acquired = false;
            try {
                $acquired = $lock->acquire();
            } catch (LockConflictedException | LockAcquiringException $e) {
                // noop
            }

            if ($acquired) {
                $this->getLogger()->info('... script is not locked');
                $lock->release();
            } else {
                // The lock class doesn't tell us how long another process is
                // holding the lock for:
                // only how long a currently acquired lock has held it.
                // This means that it can't accurately report how long another
                // script has been running. That would've been useful here!
                // We have to reach into Redis to get that information.
                $expiry = $redis->ttl($key);
                $this->getLogger()->notice(
                    sprintf(
                        '... script is locked, and will be held for at least another %d seconds',
                        $expiry
                    )
                );

                // What's the action?
                // Report: only say that the script is locked
                // Prompt: ask for user input before unlocking
                // Unlock: forcibly unlock
                $unlock = false;
                if ($this->getOpt(self::OPT_SHORT_ANSWER_YES) || in_array($lockName, $unlockFilter)) {
                    $unlock = true;
                    $this->getLogger()->notice('... unlocking automatically');
                } elseif ($this->getOpt(self::OPT_SHORT_ANSWER_NO)) {
                    $unlock = false;
                } elseif ($this->isInteractive()) {
                    // Prompt for a yes/no
                    while (true) {
                        $answer = strtolower(trim($this->readLine('Unlock? [y/N]: ')));
                        if ($answer === '') {
                            $answer = 'n';
                        }
                        if ($answer === 'n') {
                            $unlock = false;
                            break;
                        }
                        if ($answer === 'y') {
                            $unlock = true;
                            break;
                        }
                    }
                }

                if ($unlock) {
                    // Again, we can't actually tell the lock class to destroy
                    // the lock: we have to reach into Redis to destroy it.
                    $redis->del([$key]);
                }
            }
        }
    }
}
