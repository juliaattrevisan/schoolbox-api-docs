<?php
/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $size       = $_GET['size'] ?: 'maxsize';
        $resource   = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $fileSystem = $resource->getFilesystem();

        if ($fileSystem instanceof Filesystem && $fileSystem->getAdapter() instanceof AwsS3Adapter) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');

            if ($config->get('otfs_s3_presigned_urls_enabled')) {
                $preSignedUrlService = new PreSignedUrlService($config);

                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                    (!$resource->getMimetype()->getInline()) ? 'attachment;' : '',
                    ($resource->getMime()) ?: 'application/octet-stream',
                    $resource->getPath(),
                    $resource->getBasename(),
                    'private',
                    $size
                );

                $preSignedUrlService->download($preSignedUrlDataObject);
            }
        }

        $image = new Alaress\Image\Storage(
            $resource,
            $size,
            new \Alaress\Image\Transformation\Storage()
        );

        $image->stream();
        exit;
    }
} catch (Exception $e) {
    // fall through
}
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
