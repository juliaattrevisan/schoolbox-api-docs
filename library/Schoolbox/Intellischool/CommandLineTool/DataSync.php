<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool\CommandLineTool;

/**
 * Enable intellischools data syncronisation tasks to be completed on server
 */
class DataSync extends \Schoolbox\Application\CliTool\App
{
    public const LOCK_NAME = 'intellischool_sync';

    /**
     * Default timeout for symfony lock: falsey values trigger exception
     */
    private const DEFAULT_TIMEOUT = 6 * 60 * 60; //6 hours

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt('help')) {
            $this->displayHelp();
            exit;
        }

        //setup cli app for running

        $timeout     = $this->getOptAsInt('timeout:', self::DEFAULT_TIMEOUT);

        /* Force default timeout since falsey timeout means something in symfony lock
         */
        if (!$timeout) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        if (!$lock->isAcquired()) {
            exit(1);
        }

        $this->setMaxExecutionTime($timeout);

        //setup intellischool
        $deploymentId = $this->getConfig()->get('');
        $deploymentId = $this->getConfig()->get('');

        $handler = \Intellischool\SyncHandler::createWithIdAndSecret('deployment_id', 'deployment_secret');
        $handler = \Intellischool\SyncHandler::createWithOAuth2('$tokenStore', 'your_client_id', 'your_client_secret');

        $handler->setLogger($this->getLogger());

        //run sync
        try {
            $handler->doSync();
        } catch (\Exception $e) {
            $this->getLogger()->critical("It all failed, or just something...", ['message' => $e->getMessage()]);
        }
        //do something with the sync
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
        ];
    }

    /**
     * Displays a help message
     */
    private function displayHelp(): void
    {
        $timeOut           = self::DEFAULT_TIMEOUT;
        echo <<<HELP
Upload eligible documents into Kami.

Usage: upload.php -v[vv] [--perform] [--timeout=21600]
    -v:                  Verbosity level, -vvv: most verbose
    --perform:           If set, use the prod Kami API and execute real actions (otherwise run in dry-run mode)
    --timeout            Number of seconds after which a pending upload will be assumed failed
                         Defaults to {$timeOut} (6 hrs).

HELP;
    }
}
