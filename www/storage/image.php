<?php

/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\Transformation;
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $size       = $_GET['size'] ?: 'maxsize';
        $resource   = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $fileSystem = $resource->getFilesystem();

        if ($fileSystem instanceof Filesystem && $fileSystem->getAdapter() instanceof S3Adapter) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');

            if ($config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)) {
                $preSignedUrlService = new PreSignedUrlService($config);

                $resourcePath = $resource->getPath();

                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $resource,
                    Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                    ($size && strpos($resourcePath, '-') === false) ? $resourcePath . '-' . $size : $resourcePath
                );

                $preSignedUrlService->download($preSignedUrlDataObject);
            }
        }

        $transforms     = new Transformation\Storage();
        $imageProcessor = new Image\Processor($resource, $transforms->getCacheKey($size), $transforms->retrieve($size));
        $imageProcessor->load()->stream();
        exit;
    }
} catch (ConcurrentTransformationException $e) {
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], $e->getHttpStatusCode());
} catch (Exception $e) {
    // fall through
}
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
