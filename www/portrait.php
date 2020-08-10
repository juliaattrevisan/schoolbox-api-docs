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
use Alaress\Image\Implementation\Imagine;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT);

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
}

try {
    $portrait = new Image\Cache\Portrait(
        $user,
        $_GET['size'],
        $_SESSION['user'],
        (bool)$config->get('photo_sis_import'),
        (bool)$config->get('protect_student_photo'),
        (int)$config->get('protect_teacher_photo')
    );

    $fileSystem = $portrait->load()->getFilesystem();

    if ($fileSystem instanceof Filesystem &&
        $fileSystem->getAdapter() instanceof AwsS3Adapter &&
        $config->get('otfs_image_s3_presigned_urls_enabled')
    ) {

        $preSignedUrlService = new PreSignedUrlService($config);
        $resource = $portrait->load();

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT)),
            '',
            ($resource->getMime()) ?: 'application/octet-stream',
            $resource->getPath(),
            $resource->getBasename(),
            'private',
            $key
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    $image = new Image\Processor\Standard(
        $portrait,
        new Image\Transformation\Portrait(),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );

    $image->load()->stream();
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
