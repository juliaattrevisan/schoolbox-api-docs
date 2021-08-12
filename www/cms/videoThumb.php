<?php

/**
 * Generate video thumbnail
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem\Filesystem;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;
use Schoolbox\Resource\Storage\Transform\Mapper;
use Schoolbox\Resource\Storage\Transform\Model;
use Schoolbox\Video\Factory\Thumbnail as VideoThumbnailFactory;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/** @var PrefixGenerator $prefixGenerator */
$prefixGenerator = $kernel->get('cdnPrefixGenerator');

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
    /** @var SitePage $page */
    $page = $kernel->get('sitePage');
    $page->initSession();
    $storageMapper = StorageMapper::getInstance();
    $videoQueue    = null;
    try {
        $thumbnailFactory = new VideoThumbnailFactory($id, $type);
        $videoQueue       = $thumbnailFactory->produce();
    } catch (Exception $e) {
        // If anything goes wrong, fall through to sad mountain
    }

    if ($videoQueue) {
        // If it has failed transcoding, display sad mountain
        // (Do not cache, because the video should be re-encoded soon)
        if ($videoQueue->isFailed()) {
            $prefixGenerator->redirectTemporary("/images/core/video-error.png");
            exit;
        }

        // If it hasn't finished processing, display please wait image
        // (Do not cache, because the video should be done processing soon)
        if (!$videoQueue->isComplete()) {
            $prefixGenerator->redirectTemporary("/images/core/video-processing.gif");
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

            if ($filesystem instanceof Filesystem && $filesystem->getAdapter() instanceof S3Adapter) {
                /** @var IConfigLoader $config */
                $config = $kernel->get('config');

                if ($config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)) {
                    $preSignedUrlService = new PreSignedUrlService($config);

                    $preSignedUrlDataObject = $preSignedUrlService->generate(
                        $videoQueue->getStorage()->setMimetype(new Mimetype()),
                        Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT)),
                        $imageFilename,
                        'private',
                        '',
                        'image/jpeg'
                    );

                    $preSignedUrlService->download($preSignedUrlDataObject);
                }
            }

            if ($filesystem->has($imageFilename)) {
                $kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
                $image = $filesystem->get($imageFilename);
                header('Content-Type: image/jpeg');
                header("Content-Length: {$image->getSize()}");
                fpassthru($image->readStream());

                $fileName = explode('-', basename($image->getPath()));

                /**
                 * Insert video thumbnail transforms on-demand if it exists on disk but not in storage_transform table yet.
                 */
                $transformMapper = Mapper::getInstance();
                $transformModel  = $transformMapper->findByHashAndCacheKey($fileName[0], $size);

                if (!$transformModel) {
                    $transformMapper->createRecord($fileName[0], $fileName[1], $image->getSize(), Model::STATE_DONE);
                }
                exit;
            }

            // No thumbnail found in storage: fall through to sad mountain
        } catch (Exception $e) {
            // If anything else goes wrong, fall through to sad mountain
        }
    }
}

// Fallthrough for if something went wrong
$prefixGenerator->redirectTemporary("/images/core/video-error.png");
exit;
