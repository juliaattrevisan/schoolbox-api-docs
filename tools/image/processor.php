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
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';

// Need to fork before we open any db connection
// We should be able to just reconnect in the child, but that's not working for some reason.
const MAX_WORKERS = 4;                      // default maximum number of workers
const LIMIT       = 300;                    // default maximum amount of images to be processed
$workerCount = (int)getenv('MAX_WORKERS', true) ?: MAX_WORKERS;
$limit       = (int)getenv('LIMIT', true) ?: LIMIT;
if ($workerCount > $limit) {
    $workerCount = $limit;
}
$workerPids = [];
$workerIdx  = null;
for ($i = 0; $i < $workerCount; $i++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        // Unable to fork.
        echo "[manager] Unable to start image processing workers";
        exit(1);
    }
    if ($pid) {
        // Parent Process
        // Add new worker to the worker list and worker count for tracking
        $workerPids[] = $pid;
    } else {
        // Worker Process
        $workerPids = null;
        $workerIdx  = $i;
        break;
    }
}

/**
 * CLI tool for pre-converting images to the most common formats
 */
$app = new class($workerPids, $workerIdx, $limit) extends CliTool\App {
    private const TIME_INTERVAL = 15;                   // default time period in minutes to look up images for processing

    // --help: print help
    private const OPT_LONG_HELP = 'help';

    // List of expected transformations that we expect for each image
    private const EXPECTED_TRANSFORMS = [
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

    /**
     * This manager's worker's PIDs, null if a worker
     * @var int[]|null
     */
    private $workerPids;

    /**
     * This worker's index, null if the manager
     * @var int|null
     */
    private $workerIdx;

    /**
     * Total hashes to process across all workers
     * @var int
     */
    private $limit;


    /**
     * @param array|null $workerPids
     * @param int|null   $workerIdx
     * @param int        $limit
     */
    public function __construct(?array $workerPids, ?int $workerIdx, int $limit)
    {
        $this->workerPids = $workerPids;
        $this->workerIdx  = $workerIdx;
        $this->limit      = $limit;
    }


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * Returns whether this process is the manager and not a worker
     *
     * @return bool
     */
    private function isManager(): bool
    {
        return $this->workerIdx === null;
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit;
        }
        $this->isManager()
            ? $this->runManager($this->workerPids)
            : $this->runWorkerCode($this->workerIdx, $this->limit);
    }


    /**
     * Runs the manager.
     *
     * @param int[] $workerPids
     */
    private function runManager(array $workerPids): void
    {
        // Just waits for each child to finish.
        foreach ($workerPids as $workerPid) {
            pcntl_waitpid($workerPid, $status);
        }
        $this->getLogger()->info('[manager] so long, and thanks for all the fish');
    }


    /**
     * Runs the worker
     * @param int $workerIdx
     * @param int $limit
     */
    private function runWorkerCode(int $workerIdx, int $limit): void
    {
        $timeInterval = (int)getenv('TIME_INTERVAL', true) ?: self::TIME_INTERVAL;

        $hashList      = $this->getHashList($timeInterval, $limit, $workerIdx);
        $hashObjects   = Storage\Mapper::getInstance()->findByColumn('hash', $hashList);
        $filesFound    = $hashObjects->getPropertyValues('hash');
        $filesNotFound = array_diff($hashList, $filesFound);

        foreach ($filesNotFound as $hash) {
            $this->getLogger()->error(sprintf("[%s] %s: %s", posix_getpid(), $hash, 'File not found'));
        }

        foreach ($hashObjects as $hashObject) {
            $this->processHashObject($hashObject);
        }
    }


    /**
     * Worker process function
     *
     * @param Storage\Model $hashObject
     */
    private function processHashObject(Storage\Model $hashObject): void
    {
        $hash = $hashObject->getHash();
        $this->getLogger()->debug(sprintf("[%d] Processing: %s", posix_getpid(), $hash));

        // Check if each transformation size exists cached for this image, otherwise create it
        foreach (self::EXPECTED_TRANSFORMS as $transformListType => $transformList) {
            foreach ($transformList as $transformSize) {
                try {
                    if ($transformListType === 'WxH') {
                        // Random Width x Height transformations
                        $transform     = new Transformation\MyImage((int)$transformSize[0], (int)$transformSize[1]);
                        $transformSize = implode('x', $transformSize);
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
                        $this->getLogger()->info(sprintf("[%d] %s: %s", posix_getpid(), $hash . '-' . $transformSize, 'success'));
                    } else {
                        $this->getLogger()->debug(sprintf("[%d] %s: %s", posix_getpid(), $hash . '-' . $transformSize, 'already cached'));
                    }
                } catch (Exception $e) {
                    $this->getLogger()->error(sprintf("[%d] %s: %s", posix_getpid(), $hash . '-' . $transformSize, $e->getMessage()));
                    $this->getLogger()->error(sprintf("[%d] %s: %s", posix_getpid(), $hash . '-' . $transformSize, $e->getTraceAsString()));
                }
            }
        }
    }

    /**
     * Returns a list of hashes to process, exits if none found
     *
     * @param int $timeInterval
     * @param int $limit
     * @param int $workerIdx
     * @return string[]
     */
    private function getHashList(int $timeInterval, int $limit, int $workerIdx): array
    {
        // Retrieve list of images uploaded within the 15 minutes up to a max of 300
        $query = "
            SELECT `hash` FROM `storage` s
            JOIN allowedFileExtension afe ON s.ext = afe.extension
            JOIN allowedFileTypes aft
                ON afe.fileTypeID = aft.fileTypeID
                AND aft.mime LIKE 'image%'
                AND aft.mime NOT LIKE 'image%svg%'
            WHERE s.uploaded_on >= (NOW() - INTERVAL {$timeInterval} MINUTE)
            ORDER BY s.uploaded_on
            LIMIT 0, {$limit}";

        $hashList = $this->getDatabaseInternal()->executeQuery($query)->fetchAll(PDO::FETCH_COLUMN);
        $hashList = array_filter($hashList, static function (string $hash) use ($workerIdx): bool {
            // Select a subset of results to be processed by this worker
            return (int)substr(base_convert($hash, 16, 10), 0, 6) % ($workerIdx + 1) === 0;
        });
        if (!count($hashList)) {
            $this->getLogger()->info(sprintf('[%s] No images found for processing', posix_getpid()));
            exit(0);
        }
        return $hashList;
    }


    /**
     * Prints help message to STDOUT
     */
    private function printHelp(): void
    {
        // Only the manager prints help
        if (!$this->isManager()) {
            return;
        }
        echo <<<HELP

Convert image files into their commonly-accessed transforms. This preconversion trades time for storage
so that first-time-accesses don't require an on-demand transform which can sometimes slow the system
down when everyone demands image transforms simultaneously.

Usage: processor.php


HELP;
    }
};

$logLevel = (int)getenv('LOG_LEVEL', true) ?: Monolog\Logger::INFO;
$kernel   = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => $logLevel]);
$kernel->runApp();

