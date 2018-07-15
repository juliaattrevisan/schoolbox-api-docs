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
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Image Processor', array('level' => 200));
$logger = $kernel->logger();

$maxWorkers = 4;
$logger->info(sprintf("[manager] Starting Image Processor (maxWorkers: %d)", $maxWorkers));

// List of expected transformations that we expect for each image
$expectedTransform = [];
$expectedTransform['custom'] = array('maxsize','square64','square128');
$expectedTransform['WxH'] = array(
    array(100, 100),
    array(1024, 1024)
);

// Retrieve list of images uploaded within the 15 minutes up to a max of 300
$hashList = [];
$db = $kernel->get('databaseInternal');
$query = '
    SELECT `hash` FROM `storage` s
    JOIN allowedFileExtension afe ON s.ext = afe.extension
    JOIN allowedFileTypes aft
        ON afe.fileTypeID = aft.fileTypeID
        AND aft.mime LIKE "image%"
        AND aft.mime NOT LIKE "image%svg"
    WHERE s.uploaded_on >= (NOW() - INTERVAL 15 MINUTE)
    ORDER BY s.uploaded_on ASC
    LIMIT 0,300
';
$result = $db->executeQuery($query)->fetchAll();
if(is_iterable($result)){
    foreach($result as $row) {
        try {
            // Do the object lookup up front, since forked workers won't have DB access reliably
            $hashList[] = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($row['hash']);
        }
        catch (Exception $e) {
            $logger->error(sprintf("[manager] %s: %s", $hash, 'File not found'));
        }
    }
} else {
    $logger->info('[manager] No images found for processing');
    exit(0);
}

$totalHashes = count($hashList);
$logger->info(sprintf('[manager] Hashes in queue: %d', $totalHashes));
$hashItem = 0;
$workerList = [];
$workers = 0;
while ($totalHashes > 0) {
    // Check if worker processes are still running, if not remove from workerList
    while (pcntl_wait($status, WNOHANG or WUNTRACED) > 0) {
        usleep(5000);
    }
    while (list($key, $val) = each($workerList)) {
        if (!posix_kill($val, 0)) {
            unset($workerList[$key]);
            $workers = $workers - 1;
        }
    }
    $workerList = array_values($workerList);
    if ($workers >= $maxWorkers) {
        usleep(5000);
        continue;
    }

    $hash = $hashList[$hashItem]->getHash();
    $hashObject = $hashList[$hashItem];

    $pid = pcntl_fork();
    if ($pid == -1) {
        // Unable to fork.
        $logger->error("[manager] Unable to start image processing workers");
        exit(1);
    } elseif ($pid) {
        // Parent Process
        // Add new worker to the worker list and worker count for tracking
        $workerList[$hash] = $pid;
        $workers++;

        // Remove hash from the list as it has been sent for processing
        unset($hashList[$hashItem]);

        // Update work list
        $totalHashes = count($hashList);

        // Set next piece of work in the hashList array
        $hashItem++;

        usleep(5000);
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

while ($workers > 0) {
    while (pcntl_wait($status, WNOHANG or WUNTRACED) > 0) {
        usleep(5000);
    }
    while (list($key, $val) = each($workerList)) {
        if (!posix_kill($val, 0)) {
            unset($workerList[$key]);
            $workers = $workers - 1;
        }
    }
    $workerList = array_values($workerList);
    usleep(50000);
}
$logger->info('[manager] so long, and thanks for all the fish');
