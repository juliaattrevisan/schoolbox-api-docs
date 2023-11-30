#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Version\Version;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Delay the 23.1 update process by up to 5 minutes, in order to reduce load on
 * the database.
 *
 * This is relevant only to cloud, as only it contains enough instances for
 * this to be an issue.
 */
$app = new class extends CliTool\App
{
    /**
     * Delay the update.
     */
    public function run(): void
    {
        $instanceInfo = new Version($this->getConfig());
        if ($instanceInfo->isCloudHosted()) {
            $maxDelay = 120;
            $delay    = $maxDelay * (random_int(0, PHP_INT_MAX) / PHP_INT_MAX);
            $this->getLogger()->info(
                sprintf(
                    'This is a cloud instance: jittering for %0.3f (max: %d) seconds',
                    $delay,
                    $maxDelay,
                )
            );
        } else {
            $this->getLogger()->info('This is not a cloud instance: no need to jitter');
        }
    }


    /**
     * Doesn't matter who this runs as
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

$options = [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::DEBUG, CliKernelAbstract::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
