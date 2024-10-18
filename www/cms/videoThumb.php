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
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;
use Schoolbox\Resource\Storage\Transform;
use Schoolbox\Video\Factory\Thumbnail as VideoThumbnailFactory;
use Schoolbox\Video\ResourceVideo;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);

/** @var PrefixGenerator $prefixGenerator */
$prefixGenerator = $kernel->get('cdnPrefixGenerator');

$type = $_GET['type'];
$id   = $_GET['id'];

if (
    !array_key_exists('size', $_GET) ||
    !in_array($_GET['size'], [ResourceVideo::POSTER, ResourceVideo::POSTER, ResourceVideo::POSTER_LETTERBOX], true)
) {
    $_GET['size'] = ResourceVideo::THUMBNAIL;
}

$size = $_GET['size'];

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
            switch ($size) {
                case ResourceVideo::POSTER:
                    $imageFilename = $videoQueue->getPosterPath();
                    break;
                case ResourceVideo::THUMBNAIL:
                    $imageFilename = $videoQueue->getThumbnailPath();
                    break;
                case ResourceVideo::POSTER_LETTERBOX:
                    $imageFilename = $videoQueue->getPosterLetterboxPath();
                    break;
                default:
                    throw new InvalidArgumentException("Unknown size parameter '$size'.");
            }

            /** @var Filesystem $filesystem */
            $filesystem = $kernel->get('filesystem.storage');

            /** @var IConfigLoader $config */
            $config   = $kernel->get('config');
            $mimeType = 'image/jpeg';
            if (
                $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3 &&
                $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
            ) {
                $preSignedUrlService = new PreSignedUrlService($config);

                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $videoQueue->getStorage()->setMimetype(new Mimetype()),
                    Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds((int)$config->get(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT)),
                    $imageFilename,
                    ['private', 'must-revalidate'],
                    PreSignedUrlService::DISPOSITION_INLINE,
                    $mimeType,
                    // In the event a user selects 'save as', derive hinted filename from original video name + transcode size
                    sprintf('%s-%s.jpeg', $videoQueue->getStorage()->getFilename(), $size)
                );
                $preSignedUrlService->download($preSignedUrlDataObject);

                // Insert video thumbnail transforms on-demand if it exists on disk but not in storage_transform table yet.
                if ($filesystem->has($imageFilename)) {
                    [$hash, $cacheKey] = explode('-', basename($imageFilename));
                    Transform\Mapper::getInstance()->createRecord(
                        $hash,
                        $cacheKey,
                        $filesystem->fileSize($imageFilename),
                        Transform\Model::STATE_DONE,
                        'jpg'
                    );
                }
                $preSignedUrlService->download($preSignedUrlDataObject);
            }

            if ($filesystem->has($imageFilename)) {
                $kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
                header("Content-Type: $mimeType");
                header("Content-Length: {$filesystem->fileSize($imageFilename)}");
                fpassthru($filesystem->readStream($imageFilename));
                exit;
            }

            // No thumbnail found in storage: fall through to sad mountain
        } catch (InvalidPresignedUrlDurationExecption $e) {
            $kernel->outputCacheControlMaxAgeHeader(0);
        } catch (\Exception $e) {
            // If anything else goes wrong, fall through to sad mountain
        }
    }
}

if ($size === ResourceVideo::POSTER_LETTERBOX) {
    // First try falling back on the non-letterbox poster image.
    // This is for the situation where a poster_letterbox is requested for a video for which no poster_letterbox was
    // generated.  When poster_letterbox was introduced, we decided not to generate poster_letterbox images for all
    // already uploaded videos.  Therefore, videos will exist that do not have poster_letterbox images.
    $prefixGenerator->redirectPermanent("/../cms/videoThumb.php?size=poster&id=$id&type=$type");
    die;
}

// Fallthrough for if something went wrong
$prefixGenerator->redirectTemporary("/images/core/video-error.png");
exit;
