<?php
/**
 * Generate video thumbnail
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Video\Factory\Thumbnail as VideoThumbnailFactory;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

$type = $_GET['type'];
$id   = $_GET['id'];

// If no size was specified, assume thumbnail else poster
if (strlen($_GET['size']) === 0) {
    $size = 'thumbnail';
} else {
    $size = 'poster';
}

// TODO: Destroy these shenanigans

// Handle old-school brown-hole videos- this is anything with the "hash" get parameter set (believe this is
// only used in social stream now)
if (isset($_GET['hash'])) {
    $params         = $_GET;
    $params['type'] = 'storage';
    $params['id']   = $_GET['hash'];

    unset($params['hash']);

    $kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
    SitePage::localRedir('/cms/videoThumb.php?' . http_build_query($params), false, 301);
    exit;
}

if (isset($type, $id)) {
    $page          = new SitePage;
    $storageMapper = StorageMapper::getInstance();
    $videoQueue    = null;
    try {
        $thumbnailFactory = new VideoThumbnailFactory($id, $type);
        $videoQueue       = $thumbnailFactory->produce();
    } catch (Exception $e) {
        // If anything goes wrong, fall through to sad mountain
        $kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
        // Stop storm complaining
        exit;
    }

    // If it has failed transcoding, display sad mountain
    // (Do not cache, because the video should be re-encoded soon)
    if ($videoQueue->isFailed()) {
        SitePage::localRedir("/images/core/video-failed-thumb.png");
        exit;
    }

    // If it hasn't finished processing, display please wait image
    // (Do not cache, because the video should be done processing soon)
    if (!$videoQueue->isComplete()) {
        SitePage::localRedir("/images/core/video-processing-thumb.png");
        exit;
    }

    try {
        // Try to find a video record with associated poster and thumbnail links

        if ($size == 'poster') {
            $imageFilename = $videoQueue->getPosterPath();
        } else {
            $imageFilename = $videoQueue->getThumbnailPath();
        }

        /** @var \League\Flysystem\FilesystemInterface $filesystem */
        $filesystem = $videoQueue->getStorage()->getFilesystem();

        if ($filesystem->has($imageFilename)) {
            $kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
            /** \League\Flysystem\File $image */
            $image = $filesystem->get($imageFilename);
            header('Content-Type: image/jpeg');
            /** @noinspection PhpUndefinedMethodInspection */
            header("Content-Length: {$image->getSize()}");
            /** @noinspection PhpUndefinedMethodInspection */
            fpassthru($image->readStream());
            exit;
        }

        // No thumbnail found in storage: fdll through to sad mountain
//        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
//        exit;

    } catch (Exception $e) {
        // If anything else goes wrong, fall through to sad mountain

//        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
//        exit;
    }
}

// If goes wrong, head over to sad mountain
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
SitePage::redirectToBrokenImageOfSize(64, 64, 500);
