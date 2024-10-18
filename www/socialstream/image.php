<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

use Alaress\Image;
use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress_Date_Format;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\File;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

$postId = $_GET['id'];
$size   = $_GET['size'];

$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () use ($size) {
        header('Cache-Control: no-store');
        \SitePage::redirectToBrokenImageWithTransform('storage', $size, 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

try {
    $post = PostMapper::getInstance()->findById($postId);
} catch (\Schoolbox\Mapper\Exception $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
    exit;
}

// No thumbnail: show nothing.
// This is a bad request by the client.
if (!strlen($post->getThumbnail())) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size, 400);
    exit;
}

$cache = new Cache\Original();
$cache->initPost($post);

$urlDetect = new UrlDetection($post->getUrl());

if ($urlDetect->isStorageUrl()) {
    $hash    = $urlDetect->getHash();
    $storage = Storage\Mapper::getInstance()->findByHash($hash);
} elseif ($urlDetect->isResourceUrl()) {
    try {
        $file    = File\Mapper::getInstance()->findById($urlDetect->getId());
        $hash    = $file->getHash();
        $storage = Storage\Mapper::getInstance()->findByHash($hash);
    } catch (\Schoolbox\Mapper\Exception $e) {
        /* Unknown file
         */
        \SitePage::redirectToBrokenImageWithTransform('storage', $size);
        exit;
    }
} else {
    $storage = $cache->getFile();
    if (!$storage) {
        \SitePage::redirectToBrokenImageWithTransform('storage', $size, 400);
        exit;
    }
}

try {
    /** @var IConfigLoader $config */
    $config = $kernel->get('config');

    $transforms = new Image\Transformation\Storage();

    if (
        $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
        && $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
    ) {
        $preSignedUrlService   = new PreSignedUrlService($config);
        $preSignedUrlDataObject = $preSignedUrlService->generate(
            $storage,
            Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds((int)$config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
            $transforms->getTranscodePath(
                FilesystemProxy::getPathFromHash($storage->getHash()),
                $transforms->getCacheKey($size),
            ),
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    /** @var Image\ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    $processor        = $processorFactory->buildForStorage(
        $storage,
        $transforms->getCacheKey($size),
        $transforms->retrieve($size),
    );
    $processor->load()->stream();
} catch (ConcurrentTransformationException | InvalidPresignedUrlDurationExecption $e) {
    $kernel->outputResourceBeingProcessedHeader();
    \SitePage::redirectToBrokenImageWithTransform('storage', $size, $e->getHttpStatusCode());
} catch (\Throwable $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}
