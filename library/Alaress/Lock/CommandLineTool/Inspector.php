<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Lock\CommandLineTool;

use Alaress\Lock\InstanceLockFactory;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\ErrorRequeuer;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\StatusChecker;
use Schoolbox\Learning\InlineGrader\Kami\CommandLineTool\Uploader;
use Schoolbox\Pastoral\CommandLineTool\AttachmentUploader;
use Schoolbox\Video\CommandLineTool\Processor;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;

/**
 * Inspects the status of all process locks within Schoolbox.
 */
class Inspector extends CliTool\App
{
    // -h, --help: print help
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP = 'help';

    private const LOCK_NAMES = [
        ErrorRequeuer::LOCK_NAME      => 'Kami Error Requeuer (./tools/kami/requeue-errors.php)',
        StatusChecker::LOCK_NAME      => 'Kami Status Checker (./tools/kami/check-status.php)',
        Uploader::LOCK_NAME           => 'Kami Uploader (./tools/kami/upload.php)',
        AttachmentUploader::LOCK_NAME => 'Pastoral Attachment Uploader (./tools/docman/pastoral-upload.php)',
        Processor::LOCK_NAME          => 'Video Processor (./tools/video/processor.php)',
    ];


    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP,];
    }


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP,];
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
        $help   = "$white

NAME:
    Lock inspector

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    Tool for inspecting process locks.

    Due to limitations within the lock implementation, this tool cannot:
    * report how long a lock has to run before it expires
    * break a lock it could not acquire

    $yellow-h, --help$white
        Print this help and exit

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


        // otherwise, report on locks
        foreach (self::LOCK_NAMES as $lockName => $lockDescription) {
            $this->getLogger()->notice(sprintf('Testing lock: %s, script: %s', $lockName, $lockDescription));

            /** @noinspection PhpUnhandledExceptionInspection */
            /** @var InstanceLockFactory $lockFactory */
            $lockFactory = $this->getDi()->get('lockFactory');
            $lock        = $lockFactory->createLock($lockName, 0);

            // Is this lock acquirable?
            $acquired = false;
            try {
                // TODO update to version 5.1 once PHP 7.4 is in use, then acquire read lock
                $acquired = $lock->acquire();
            } catch (LockConflictedException|LockAcquiringException $e) {
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
                $this->getLogger()->notice('... script is locked');

                // Also: the lock class can't break a lock it did not acquire.
                // This is probably sensible behavior, but it does mean that we
                // have to rely upon sensible settings being set for the lock.
            }
        }
    }
}
