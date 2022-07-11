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

        try {
            $lock = $this->acquireLock(self::LOCK_NAME, $timeout);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        $this->setMaxExecutionTime($timeout);

        //setup intellischool
        $deploymentId = $this->getConfig()->get('intellischool_deployment_id');
        $deploymentSecret = $this->getConfig()->get('intellischool_deployment_secret');

        //confirm the api details are set, as it will error out if they are not set.
        /**
         * if a user un-sets the values in admin it can show as '' not null,
         * so lets say the value is a string so if it is null it will show cast to ''
         */
        if ('' === trim((string)$deploymentId) || '' === trim((string)$deploymentSecret)) {
            $this->getLogger()->info("Deployment ID or Secret is not set. Please set and re run this script.");
            $lock->release();
            exit(1);
        }
        $handler = \Intellischool\SyncHandler::createWithIdAndSecret($deploymentId, $deploymentSecret);

        $handler->setLogger($this->getLogger());

        //run sync
        try {
            $handler->doSync();
        } catch (\Exception $e) {
            $this->getLogger()->critical($e->getMessage());
            $lock->release();
        }
        $lock->release();
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'timeout:',
        ];
    }

    /**
     * Displays a help message
     */
    private function displayHelp(): void
    {
        $timeOut           = self::DEFAULT_TIMEOUT;

        /* https://stackoverflow.com/questions/3172332/convert-seconds-to-hourminutesecond
                */
        $duration = floor($timeOut / 3600) . gmdate(":i:s", $timeOut % 3600);

        echo <<<HELP
Intellischools server data sync

Usage: dataSync.php -v[vv] [--timeout:21600]
    -v                   Verbosity level, -vvv most verbose
    --timeout:           Number of seconds after which a pending jobs will be assumed failed
                         Defaults to {$timeOut} ({$duration} hrs).

HELP;
    }
}
