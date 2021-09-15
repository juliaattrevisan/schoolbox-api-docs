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
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'] ?? '', 504);
    }),
    0 // Max Priority
);

/** @var IConfigLoader $config */
$config = $kernel->getDependencyInjector()->get('config');

/* If current user is not available return permission denied */
if (!($_SESSION['user'] instanceof Schoolbox\User)) {
    SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}

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

    $fileSystem = $storageModel->getFilesystem();

    $portrait  = new Image\Transformation\Portrait();
    $cacheKey  = (isset($_GET['size'])) ? $portrait->getCacheKey($_GET['size']) : null;

    if (
        $fileSystem instanceof Filesystem &&
        $fileSystem->getAdapter() instanceof S3Adapter &&
        $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
    ) {
        $preSignedUrlService = new PreSignedUrlService($config);

        $portraitPath = $storageModel->getPath();

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            $storageModel,
            Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT)),
            $portrait->getTranscodePath($portraitPath, $cacheKey)
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    $image = new Image\Processor($storageModel, $cacheKey, $portrait->retrieve($_GET['size']));
    $image->load()->stream();
} catch (ConcurrentTransformationException $e) {
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
