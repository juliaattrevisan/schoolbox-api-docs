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

$logger->info("Starting Image Processor");

$expectedTransform['custom'] = array('maxsize','square64','square128');
$expectedTransform['WxH'] = array(
    array(100, 100),
    array(1024, 1024)
);

$hashList = array();
// TODO: This gets the last 250 uploaded images to check, maybe should be smarter
$db = $kernel->get('databaseInternal');
$query = '
    SELECT `hash` FROM `storage` s
    JOIN allowedFileExtension afe ON s.ext = afe.extension
    JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID AND aft.mime LIKE "image%"
    ORDER BY s.uploaded_on ASC
    LIMIT 0,250
';
$result = $db->executeQuery($query)->fetchAll();
if(count($result) > 0){
    foreach($result as $row) {
        $hashList[] = $row['hash'];
    }
}

$totalHashes = count($hashList);
if(!$totalHashes > 0){
    $logger->info('No images found for processing');
    exit(0);
} else {
    $logger->info(sprintf('Hashes in queue: %d', $totalHashes));
}

foreach ($hashList as $hash) {
    try {
        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($hash);
    }
    catch (Exception $e) {
        $logger->error(sprintf("%s %s: %s", $hash, '404', 'File not found'));
    }
    foreach ($expectedTransform as $transformListType => $transformList) {
        foreach ($transformList as $transformSize) {
            try {
                if ($transformListType == 'WxH') {
                    // Random Width x Hight transformations
                    $transform = new Transformation\MyImage((int)$transformSize[0], (int)$transformSize[1]);
                    $transformSize = implode($transformSize, 'x');
                    $cache = new Image\Cache\Storage($resource, $transformSize);
                    $image = new Image\Processor\Freeform(
                        $cache,
                        $transform,
                        new Imagine\Filetype\Factory(),
                        new Imagine\Processor\Strategy\Factory()
                    );
                } else {
                    // Preset transformations
                    $transform = new Image\Transformation\Storage();
                    $cache = new Image\Cache\Storage($resource, $transformSize);
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
                    $logger->info(sprintf("%s %s: %s", $hash . '-' . $transformSize, '200', 'Transformed successfully'));
                } else {
                    $logger->notice(sprintf("%s %s: %s", $hash . '-' . $transformSize, '301', 'Cached transform found'));
                }
                unset($image, $cache, $transform);
            }
            catch (Exception $e) {
                $logger->error(sprintf("%s %s: %s", $hash . '-' . $transformSize, '500', $e->getMessage()));
            }
        }
    }
    unset($resource);
}