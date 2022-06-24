<?php

/**
 * Optimus Prime
 * Autobots, transform and roll out!
 *
 * Example usages of Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\ProcessorFactory;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/* All geosnapshot pictures are constrain200 transformation
 */
const TRANSFORM_KEY = 'constrain200';

/* Check that the headers matched what we expected
 */
$headers = getallheaders();

/** @var IConfigLoader $config */
$config = $kernel->getDependencyInjector()->get('config');

if (
    !$config->get('geosnapshot_enabled') ||
    $config->get('geosnapshot_api_key') === '' ||
    $config->get('geosnapshot_api_key') !== $headers['X-Api-Key']
) {
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY, 404);
    exit;
}

$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY, 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

/* Accept both id and userId params
 */
$userMapper = \Schoolbox\UserMapper::getInstance();

try {
    if (isset($_GET['id']) && (int)$_GET['id']) {
        $user = $userMapper->findById($_GET['id']);
    } else {
        $user = $userMapper->findByExternalId($_GET['userId']);
    }
} catch (\Schoolbox\Mapper\Exception $e) {
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY);
    exit;
}

try {
    /* Simulate an unrestricted superuser fetching the image
     * Yes, that third arg is an inty bool...
     */
    $storageModel = Image\Cache\Portrait::getStorageModel(
        $user,
        UserMapper::getInstance()->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN),
        false,
        false,
        0
    );


    $portrait  = new Image\Transformation\Portrait();
    $cacheKey  = $portrait->getCacheKey(TRANSFORM_KEY);
    if (
        $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3
        && $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
    ) {
        $preSignedUrlService = new PreSignedUrlService($config);

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            $storageModel,
            Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT)),
            $portrait->getTranscodePath(
                FilesystemProxy::getPathFromHash($storageModel->getHash()),
                $cacheKey,
            ),
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    /** @var ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    $image            = $processorFactory->buildForStorage(
        $storageModel,
        $cacheKey,
        $portrait->retrieve(TRANSFORM_KEY),
    );
    $image->load()->stream();
} catch (ConcurrentTransformationException | InvalidPresignedUrlDurationExecption $e) {
    // This transformation is already being processed.
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY, $e->getHttpStatusCode());
} catch (\RuntimeException $e) {
    /* When you cannot access the requested image (access denied)
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY, 403);
} catch (\InvalidArgumentException $e) {
    /* Image/Portrait not found
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY, 404);
} catch (Exception $e) {
    /* All other exceptions
    * BadMethodCallException
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', TRANSFORM_KEY);
}
