<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

/* Preserve old checks
 */
session_start();

/* Choose a caching method to use
 */
$key = $_GET['width'] . 'x' . $_GET['height'];

try {
    $file = Schoolbox\Resource\File\Mapper::getInstance()->findById($_GET['id']);
    /* See if you can view this image
     */
    $groupHandler = new Group();

    if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 403);
    }

} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
}

/* Process and stream image
 */
try {
    /** @var Schoolbox\Resource\File\Model $file */
    /** @var Schoolbox\Resource\Storage\Model $resource */
    $resource   = $file->getStorage();
    /** @var IConfigLoader $config */
    $config     = $kernel->getDependencyInjector()->get('config');
    $fileSystem = $resource->getFilesystem();

    if ($fileSystem instanceof Filesystem &&
        $fileSystem->getAdapter() instanceof AwsS3Adapter &&
        $config->get('otfs_s3_presigned_urls_enabled')
    ) {
        $preSignedUrlService = new PreSignedUrlService($config);

        $preSignedUrlDataObject = $preSignedUrlService->generate(
            Alaress_Date_Format::now()->addDays(1),
            (!$resource->getMimetype()->getInline() || $_GET['save']) ? 'attachment;' : '',
            ($resource->getMime()) ?: 'application/octet-stream',
            $resource->getPath(),
            $resource->getBasename(),
            ($_GET['cache'] ?? '0') ? 'private' : 'no-store'
        );

        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    $imageProcessor = new Freeform(
        new Cache\Storage($resource, $key),
        new Transformation\MyImage($_GET['width'], $_GET['height']),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $imageProcessor->load()->stream();
} catch (SchoolboxNotFoundException $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 500);
}
