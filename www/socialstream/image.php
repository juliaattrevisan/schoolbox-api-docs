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
use Alaress\Image\Implementation\Imagine;
use Alaress_Date_Format;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\FilesystemInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

$postId = $_GET['id'];
$size   = $_GET['size'];

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

} else {
    $storage = $cache->getFile();
}

try {
    $fileSystem = $storage->getFilesystem();

    /** @var IConfigLoader $config */
    $config = $kernel->get('config');
    if ($fileSystem instanceof FilesystemInterface &&
        $fileSystem->getAdapter() instanceof AwsS3Adapter &&
        $config->get('otfs_image_s3_presigned_urls_enabled')
    ) {

        $preSignedUrlService = new PreSignedUrlService($config);

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
            '',
            ($storage->getMime()) ?: 'application/octet-stream',
            $storage->getPath(),
            $storage->getBasename(),
            'private',
            $size
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    $cache     = new Image\Cache\Storage($storage, $size);
    $processor = new Image\Processor\Standard(
        $cache,
        new Image\Transformation\Storage(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $processor->load()->stream();
} catch (\Throwable $e) {
    \SitePage::redirectToBrokenImageWithTransform('storage', $size);
}
