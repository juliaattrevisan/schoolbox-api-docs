#!/usr/bin/php
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

$app = new class extends CliTool\App
{
    private const DEFAULT_TIME_INTERVAL_MINUTES  = 60;                   // default time period in minutes to look up images for processing
    private const DEFAULT_IMAGE_COUNT_LIMIT      = 300;                  // default maximum amount of images to be processed
    private const DEFAULT_ATTEMPTS_PER_TRANSFORM = 3;                    // default maximum times to attempt each transformation

    // --help: print help
    private const OPT_LONG_HELP = 'help';
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_RETRY_FAILED = 'retry-failed';
    private const OPT_LONG_TIME_INTERVAL = 'time-interval:';
    private const OPT_LONG_MAX_WORKERS = 'max-workers:';
    private const OPT_LONG_LIMIT = 'limit:';
    private const OPT_LONG_MAX_ATTEMPTS = 'max-attempts:';

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h'];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_RETRY_FAILED,
            self::OPT_LONG_TIME_INTERVAL,
            self::OPT_LONG_MAX_WORKERS,
            self::OPT_LONG_LIMIT,
            self::OPT_LONG_MAX_ATTEMPTS,
        ];
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

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP

    Convert image files into their commonly-accessed transforms. This preconversion trades time for storage
    so that first-time-accesses don't require an on-demand transform which can sometimes slow the system
    down when everyone demands image transforms simultaneously.

    Usage: tools/image/processor.php

    Options:
        -h,--help           Print this help message and exit
        -v                  Log with additional verbosity. Can be repeated up to 3 times.
        --retry-failed      If true, just retry previously failed transforms. Eg if hash file has now been restored.
        --time-interval N   Produce standard transforms for storages files uploaded in last N minutes. Default: 60
        --max-workers N     Maximum number of concurrent workers. Defaults to CPU count
        --limit N           Maximum number of storage files to transform
        --max-attempts N    Maximum number of times to attempt a transformation if it throws a catchable exception

    HELP;
            exit;
        }
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
