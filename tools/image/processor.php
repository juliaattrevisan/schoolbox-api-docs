#!/usr/bin/php
<?php
/**
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Transformation;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI tool for pre-converting images to the most common formats
 */
$app = new class extends CliTool\App
{

    private const MAX_WORKERS = 4;                      // default maximum number of workers
    private const TIME_INTERVAL = 15;                   // default time period in minutes to look up images for processing
    private const LIMIT = 300;                          // default maximum amount of images to be processed
    private const SPINLOCK_WAIT_TIME = 5000;            // default spinlock wait time in microseconds
    private const WORKER_CLEANUP_TIME = 50000;          // default wait timeout for worker cleanup in microseconds

    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Convert image files into their commonly-accessed transforms. This preconversion trades time for storage
so that first-time-accesses don't require an on-demand transform which can sometimes slow the system
down when everyone demands image transforms simultaneously.

Usage: processor.php


HELP;
            exit;
        }


        // Override default values with ENV variables
        $maxWorkers   = (int)getenv('MAX_WORKERS', true) ?: self::MAX_WORKERS;
        $timeInterval = (int)getenv('TIME_INTERVAL', true) ?: self::TIME_INTERVAL;
        $limit        = (int)getenv('LIMIT', true) ?: self::LIMIT;

        $logger = $this->getLogger();
        $logger->info(sprintf("[manager] Starting Image Processor (maxWorkers: %d)", $maxWorkers));

        // List of expected transformations that we expect for each image
        $expectedTransform = [
            'custom' => [
                'maxsize',
                'square64',
                'square128',
            ],
            'WxH'    => [
                [100, 100],
                [2048, 2048],
            ],
        ];

        // Retrieve list of images uploaded within the 15 minutes up to a max of 300
        $hashList = [];
        $db       = $this->getDatabaseInternal();
        $query    = "
    SELECT `hash` FROM `storage` s
    JOIN allowedFileExtension afe ON s.ext = afe.extension
    JOIN allowedFileTypes aft
        ON afe.fileTypeID = aft.fileTypeID
        AND aft.mime LIKE 'image%'
        AND aft.mime NOT LIKE 'image%svg%'
    WHERE s.uploaded_on >= (NOW() - INTERVAL {$timeInterval} MINUTE)
    ORDER BY s.uploaded_on
    LIMIT 0, {$limit}
";
        $result   = $db->executeQuery($query)->fetchAll();
        if (is_iterable($result) && count($result)) {
            foreach ($result as $row) {
                $hashList[] = $row['hash'];
            }
        } else {
            $logger->info('[manager] No images found for processing');
            exit(0);
        }

        $hashObjects   = \Schoolbox\Resource\Storage\Mapper::getInstance()
                                                           ->findByColumn('hash', $hashList)
                                                           ->indexBy('hash');
        $filesFound    = $hashObjects->getPropertyValues('hash');
        $filesNotFound = array_diff($hashList, $filesFound);

        if (count($filesNotFound)) {
            foreach ($filesNotFound as $hash) {
                $logger->error(sprintf("[manager] %s: %s", $hash, 'File not found'));
                unset($hashList[$hash]);
            }
        }

        $logger->info(sprintf('[manager] Hashes in queue: %d', count($hashList)));
        $hashItem = 0;
        $workers  = new class
        {

            public $count = 0;
            public $list = [];
        };
        while (count($hashList) > 0) {
            $this->workerCleanup($workers);
            if ($workers->count >= $maxWorkers) {
                usleep(self::SPINLOCK_WAIT_TIME);
                continue;
            }

            $hash       = $hashList[$hashItem];
            $hashObject = $hashObjects->getOffset($hash);

            $pid = pcntl_fork();
            if ($pid === -1) {
                // Unable to fork.
                $logger->error("[manager] Unable to start image processing workers");
                exit(1);
            }
            if ($pid) {
                // Parent Process
                // Add new worker to the worker list and worker count for tracking
                $workers->list[$hash] = $pid;
                $workers->count++;

                // Remove hash from the list as it has been sent for processing
                unset($hashList[$hashItem]);

                // Set next piece of work in the hashList array
                $hashItem++;

                usleep(self::SPINLOCK_WAIT_TIME);
            } else {
                // Start of Worker Process
                $workerId = posix_getpid();
                $logger->debug(sprintf("[%d] Processing: %s", $workerId, $hash));

                // Check if each transformation size exists cached for this image, otherwise create it
                foreach ($expectedTransform as $transformListType => $transformList) {
                    foreach ($transformList as $transformSize) {
                        try {
                            if ($transformListType === 'WxH') {
                                // Random Width x Height transformations
                                $transform     = new Transformation\MyImage((int)$transformSize[0], (int)$transformSize[1]);
                                $transformSize = implode($transformSize, 'x');
                                $cache         = new Image\Cache\Storage($hashObject, $transformSize);
                                $image         = new Image\Processor\Freeform(
                                    $cache,
                                    $transform,
                                    new Imagine\Filetype\Factory(),
                                    new Imagine\Processor\Strategy\Factory()
                                );
                            } else {
                                // Preset transformations
                                $transform = new Image\Transformation\Storage();
                                $cache     = new Image\Cache\Storage($hashObject, $transformSize);
                                $image     = new Image\Processor\Standard(
                                    $cache,
                                    $transform,
                                    new Imagine\Filetype\Factory(),
                                    new Imagine\Processor\Strategy\Factory()
                                );
                            }
                            $image->load();

                            if (!$image->isCached()) {
                                $image->save();
                                $logger->info(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, 'success'));
                            } else {
                                $logger->debug(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, 'already cached'));
                            }
                        } catch (Exception $e) {
                            $logger->error(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, $e->getMessage()));
                            $logger->error(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, $e->getTraceAsString()));
                        }
                    }
                }
                exit(0);
            }
        }

        while ($workers->count > 0) {
            $this->workerCleanup($workers);
            usleep(self::WORKER_CLEANUP_TIME);
        }
        $logger->info('[manager] so long, and thanks for all the fish');
    }


    private function workerCleanup($workers): void
    {
        // Check if worker processes are still running, if not remove from workerList
        while (pcntl_wait($status, WNOHANG or WUNTRACED) > 0) {
            usleep(self::SPINLOCK_WAIT_TIME);
        }
        while (list($hash, $val) = each($workers->list)) {
            if (!posix_kill($val, 0)) {
                unset($workers->list[$hash]);
                $workers->count--;
            }
        }
        $workers->list = array_values($workers->list);
    }
};

$logLevel = (int)getenv('LOG_LEVEL', true) ?: Monolog\Logger::INFO;
$kernel   = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => $logLevel]);
$kernel->runApp();

