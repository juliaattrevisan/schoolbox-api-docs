<?php

/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\ProcessorFactory;
use Alaress\Image\Transformation;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Manager;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $size = $_GET['size'] ?? 'maxsize';
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $kernel->get('filesystem.storageProxy');
        $resource        = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);
        $transforms      = new Transformation\Storage();
        if ($filesystemProxy->usesS3Adapter()) {
            /** @var IConfigLoader $config */
            $config = $kernel->get('config');

            if ($config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)) {
                $preSignedUrlService    = new PreSignedUrlService($config);
                $preSignedUrlDataObject = $preSignedUrlService->generate(
                    $resource,
                    Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                    $transforms->getTranscodePath(
                        Manager::getPathFromHash($resource->getHash()),
                        $transforms->getCacheKey($size),
                    ),
                );

                $preSignedUrlService->download($preSignedUrlDataObject);
            }
        }

        $kernel->getShutdownHandler()->registerFunction(
            new ResourceLimitExceededHandler(function () use ($size) {
                header('Cache-Control: no-store');
                \SitePage::redirectToBrokenImageWithTransform('storage', $size, 504);
            }),
            ShutdownHandler::PRIORITY_HIGHEST,
        );

        /** @var ProcessorFactory $processorFactory */
        $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
        $imageProcessor   = $processorFactory->buildForStorage(
            $resource,
            $transforms->getCacheKey($size),
            $transforms->retrieve($size),
        );
        $imageProcessor->load()->stream();
    }
} catch (ConcurrentTransformationException|InvalidPresignedUrlDurationExecption $e) {
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], $e->getHttpStatusCode());
} catch (Exception $e) {
    // fall through
}
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
