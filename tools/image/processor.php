#!/usr/bin/env php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Image;
use Alaress\Lumberjack\Logger;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\BaseApp
{
    private const DEFAULT_TIME_INTERVAL_MINUTES  = 60;                   // default time period in minutes to look up images for processing
    private const DEFAULT_IMAGE_COUNT_LIMIT      = 300;                  // default maximum amount of images to be processed
    private const DEFAULT_ATTEMPTS_PER_TRANSFORM = 3;                    // default maximum times to attempt each transformation

    private const OPT_LONG_RETRY_FAILED  = 'retry-failed';
    private const OPT_LONG_TIME_INTERVAL = 'time-interval:';
    private const OPT_LONG_MAX_WORKERS   = 'max-workers:';
    private const OPT_LONG_LIMIT         = 'limit:';
    private const OPT_LONG_MAX_ATTEMPTS  = 'max-attempts:';

    /** @inheritdoc */
    protected function getName(): string
    {
        return 'Image Processor';
    }

    /** @inheritdoc */
    protected function getDescription(): string
    {
        return 'Converts image files into their commonly-accessed transforms. This pre-conversion trades time for storage so that first-time accesses do not require an on-demand transform.';
    }

    /** @inheritdoc */
    protected function setOptions(): CliTool\BaseApp
    {
        $this->optionCollection->add(self::OPT_LONG_RETRY_FAILED, 'Retry previously failed transforms instead of processing new ones');
        $this->optionCollection->add(self::OPT_LONG_TIME_INTERVAL, 'Produce transforms for files uploaded in the last N minutes (default: ' . self::DEFAULT_TIME_INTERVAL_MINUTES . ')');
        $this->optionCollection->add(self::OPT_LONG_MAX_WORKERS, 'Maximum number of concurrent workers (default: 50% of CPU count)');
        $this->optionCollection->add(self::OPT_LONG_LIMIT, 'Maximum number of storage files to transform (default: ' . self::DEFAULT_IMAGE_COUNT_LIMIT . ')');
        $this->optionCollection->add(self::OPT_LONG_MAX_ATTEMPTS, 'Maximum number of times to attempt a transformation (default: ' . self::DEFAULT_ATTEMPTS_PER_TRANSFORM . ')');
        return $this;
    }

    /** @inheritdoc */
    protected function hasPerformMode(): bool
    {
        return false;
    }

    /**
     * `tools/image/worker.php` needs www-data specifically to run, so enforce that here
     *
     * @return string
     */
    public function getRequiredProcessUser(): string
    {
        return 'www-data';
    }

    public function run(): void
    {
        $retryFailed = (bool)$this->getOpt(self::OPT_LONG_RETRY_FAILED);
        $timeInterval = $this->getOptAsInt(self::OPT_LONG_TIME_INTERVAL) ?: self::DEFAULT_TIME_INTERVAL_MINUTES;
        $workerCount = $this->getOptAsInt(self::OPT_LONG_MAX_WORKERS) ?: (int)max(1, ((int)shell_exec('nproc') * 0.50));
        $imageCountLimit = $this->getOptAsInt(self::OPT_LONG_LIMIT) ?: self::DEFAULT_IMAGE_COUNT_LIMIT;
        $attemptsPerTransform = $this->getOptAsInt(self::OPT_LONG_MAX_ATTEMPTS) ?: self::DEFAULT_ATTEMPTS_PER_TRANSFORM;

        proc_nice(10); // Always run with lower priority

        // TODO inject lock inspector instead of cache

        /** @var Logger $logger */
        $logger = $this->getLogger();

        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis.cache');
        $parent = new Image\Processor\Manager(
            $timeInterval,
            $imageCountLimit,
            $workerCount,
            $logger,
            $retryFailed,
            $attemptsPerTransform,
            $logger->getThreshold() ?? 0, // get logging threshold or fallback to error only
            $redis
        );
        $isInterrupted = $parent->run();
        exit($isInterrupted ? 1 : 0);
    }
};

$kernel = new CliTool\Kernel($app, [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
]);
$kernel->runApp();
