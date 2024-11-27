<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\ProcessorFactory;
use Alaress\Image\Transformation\MyImage;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';

[$width, $height] = MyImage::ensureDimensionsAreReasonable($_GET['width'] ?? null, $_GET['height'] ?? null);

$kernel = new Kernel();
$kernel->setupSentryTrace(__FILE__);
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () use ($width, $height) {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImageOfSize($width, $height, 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);
/* Choose a caching method to use
 */


try {
    $file = Schoolbox\Resource\File\Mapper::getInstance()->findById($_GET['id']);
    /* See if you can view this image
     */
    $groupHandler = new Group();

    if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
        SitePage::redirectToBrokenImageOfSize($width, $height, 403);
    }
} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($width, $height, 404);
}

/* Process and stream image
 */
$myImage  = new MyImage();
$cacheKey = $myImage->getCacheKey($width, $height);
try {
    /** @var Schoolbox\Resource\File\Model $file */
    /** @var Schoolbox\Resource\Storage\Model $resource */
    $resource = $file->getStorage();
    /** @var IConfigLoader $config */
    $config = $kernel->getDependencyInjector()->get('config');
    if (
        $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
        && $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
    ) {
        $preSignedUrlService = new PreSignedUrlService($config);

        $resourcePath = FilesystemProxy::getPathFromHash($resource->getHash());

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            $resource,
            Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds((int)$config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
            $myImage->getTranscodePath($resourcePath, $cacheKey)
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    /** @var ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    $transform        = $myImage->retrieve($width, $height);
    $imageProcessor   = $processorFactory->buildForStorage(
        $resource,
        $cacheKey,
        $transform,
    );
    $imageProcessor->load()->stream();
} catch (ConcurrentTransformationException | InvalidPresignedUrlDurationExecption $e) {
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImageOfSize($width, $height, $e->getHttpStatusCode());
} catch (SchoolboxNotFoundException $e) {
    SitePage::redirectToBrokenImageOfSize($width, $height, 404);
} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($width, $height, 500);
}
