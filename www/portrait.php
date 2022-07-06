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
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'] ?? '', 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

/** @var IConfigLoader $config */
$config = $kernel->getDependencyInjector()->get('config');

/* If current user is not available return permission denied */
if (!(($_SESSION['user'] ?? null) instanceof Schoolbox\User)) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}

/* Accept both id and userId params
 */
$userMapper = \Schoolbox\UserMapper::getInstance();

$user = null;
try {
    if (isset($_GET['id']) && (int)$_GET['id']) {
        $user = $userMapper->findById($_GET['id']);
    } elseif (isset($_GET['userId'])) {
        $user = $userMapper->findByExternalId($_GET['userId']);
    }
} catch (\Schoolbox\Mapper\Exception $e) {
    // $user will be null
}
if (!$user) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size']);
    exit;
}

try {
    $storageModel = Image\Cache\Portrait::getStorageModel(
        $user,
        $_SESSION['user'],
        (bool)$config->get('photo_sis_import'),
        (bool)$config->get('protect_student_photo'),
        (int)$config->get('protect_teacher_photo')
    );


    $portrait  = new Image\Transformation\Portrait();
    $cacheKey  = (isset($_GET['size'])) ? $portrait->getCacheKey($_GET['size']) : null;
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

    /** @var Image\ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    $image            = $processorFactory->buildForStorage(
        $storageModel,
        $cacheKey,
        $portrait->retrieve($_GET['size'] ?? ''),
    );
    $image->load()->stream();
} catch (ConcurrentTransformationException | InvalidPresignedUrlDurationExecption $e) {
    // This transformation is already being processed.
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], $e->getHttpStatusCode());
} catch (\RuntimeException $e) {
    /* When you cannot access the requested image (access denied)
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
} catch (\InvalidArgumentException $e) {
    /* Image/Portrait not found
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 404);
} catch (Exception $e) {
    /* All other exceptions
    * BadMethodCallException
    */
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size']);
}
