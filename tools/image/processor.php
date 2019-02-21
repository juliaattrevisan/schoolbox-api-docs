#!/usr/bin/php
<?php
/**
 * CLI tool for pre-converting images to the most common formats
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   2018 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */
use Alaress\Database\Connector\IConnector;
use Alaress\Image;
use Alaress\Image\Implementation\Imagine;
use Schoolbox\Resource\Storage;
use Alaress\Image\Transformation;

require_once __DIR__ . '/../../conf/core/require.php';

define('LOG_LEVEL', 200); // default log level to INFO
define('MAX_WORKERS', 4); // default maximum number of workers
define('TIME_INTERVAL', 15); // time period in minutes to look up images for processing
define('LIMIT', 300); // maximum amount of images to be processed
define('SPINLOCK_WAIT_TIME', 5000); // default spinlock wait time in microseconds
define('WORKER_CLEANUP_TIME', 50000); // default wait timeout for worker cleanup in microseconds

// Override default values from ENV variables
$logLevel = (int)getenv('LOG_LEVEL', true) ?: LOG_LEVEL;
$maxWorkers = (int)getenv('MAX_WORKERS', true) ?: MAX_WORKERS;
$timeInterval = (int)getenv('TIME_INTERVAL', true) ?: TIME_INTERVAL;
$limit = (int)getenv('LIMIT', true) ?: LIMIT;

$kernel = new \Schoolbox\Application\Daemon\Kernel('Image Processor', array('level' => $logLevel));
$logger = $kernel->logger();

$logger->info(sprintf("[manager] Starting Image Processor (maxWorkers: %d)", $maxWorkers));

// List of expected transformations that we expect for each image
$expectedTransform = [
    'custom' => [
        'maxsize', 'square64', 'square128'
    ],
    'WxH' => [
        [100, 100], [1024, 1024]
    ]
];

// Retrieve list of images uploaded within the 15 minutes up to a max of 300
$hashList = [];
$db = $kernel->get('databaseInternal');
$query = "
    SELECT `hash` FROM `storage` s
    JOIN allowedFileExtension afe ON s.ext = afe.extension
    JOIN allowedFileTypes aft
        ON afe.fileTypeID = aft.fileTypeID
        AND aft.mime LIKE 'image%'
        AND aft.mime NOT LIKE 'image%svg%'
    WHERE s.uploaded_on >= (NOW() - INTERVAL {$timeInterval} MINUTE)
    ORDER BY s.uploaded_on ASC
    LIMIT 0,{$limit}
";
$result = $db->executeQuery($query)->fetchAll();
if(is_iterable($result)){
    foreach($result as $row) {
        $hashList[] = $row['hash'];
    }
} else {
    $logger->info('[manager] No images found for processing');
    exit(0);
}

$hashObjects = \Schoolbox\Resource\Storage\Mapper::getInstance()
    ->findByColumn('hash', $hashList)
    ->indexBy('hash');
$filesFound = $hashObjects->getPropertyValues('hash');
$filesNotFound = array_diff($hashList, $filesFound);

if (count($filesNotFound)) {
    foreach($filesNotFound as $hash) {
        $logger->error(sprintf("[manager] %s: %s", $hash, 'File not found'));
        unset($hashList[$hash]);
    }
}

function workerCleanup($workers) {
    // Check if worker processes are still running, if not remove from workerList
    while (pcntl_wait($status, WNOHANG or WUNTRACED) > 0) {
        usleep(SPINLOCK_WAIT_TIME);
    }
    while (list($hash, $val) = each($workers->list)) {
        if (!posix_kill($val, 0)) {
            unset($workers->list[$hash]);
            $workers->count--;
        }
    }
    $workers->list = array_values($workers->list);
}

$logger->info(sprintf('[manager] Hashes in queue: %d', \count($hashList)));
$hashItem = 0;
$workers = new class {
    public $count = 0;
    public $list = [];
};
while (count($hashList) > 0) {
    workerCleanup($workers);
    if ($workers->count >= $maxWorkers) {
        usleep(SPINLOCK_WAIT_TIME);
        continue;
    }

    $hash = $hashList[$hashItem];
    $hashObject = $hashObjects->getOffset($hash);

    $pid = pcntl_fork();
    if ($pid == -1) {
        // Unable to fork.
        $logger->error("[manager] Unable to start image processing workers");
        exit(1);
    } elseif ($pid) {
        // Parent Process
        // Add new worker to the worker list and worker count for tracking
        $workers->list[$hash] = $pid;
        $workers->count++;

        // Remove hash from the list as it has been sent for processing
        unset($hashList[$hashItem]);

        // Set next piece of work in the hashList array
        $hashItem++;

        usleep(SPINLOCK_WAIT_TIME);
    } else {
        // Start of Worker Process
        $workerId = posix_getpid();
        $logger->debug(sprintf("[%d] Processing: %s", $workerId, $hash));

        // Check if each transformation size exists cached for this image, otherwise create it
        foreach ($expectedTransform as $transformListType => $transformList) {
            foreach ($transformList as $transformSize) {
                try {
                    if ($transformListType == 'WxH') {
                        // Random Width x Hight transformations
                        $transform = new Transformation\MyImage((int)$transformSize[0], (int)$transformSize[1]);
                        $transformSize = implode($transformSize, 'x');
                        $cache = new Image\Cache\Storage($hashObject, $transformSize);
                        $image = new Image\Processor\Freeform(
                            $cache,
                            $transform,
                            new Imagine\Filetype\Factory(),
                            new Imagine\Processor\Strategy\Factory()
                        );
                    } else {
                        // Preset transformations
                        $transform = new Image\Transformation\Storage();
                        $cache = new Image\Cache\Storage($hashObject, $transformSize);
                        $image = new Image\Processor\Standard(
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
                }
                catch (Exception $e) {
                    $logger->error(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, $e->getMessage()));
                    $logger->error(sprintf("[%d] %s: %s", $workerId, $hash . '-' . $transformSize, $e->getTraceAsString()));
                }
            }
        }
        exit(0);
    }
}

while ($workers->count > 0) {
    workerCleanup($workers);
    usleep(WORKER_CLEANUP_TIME);
}
$logger->info('[manager] so long, and thanks for all the fish');
