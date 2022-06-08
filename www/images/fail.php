<?php

/**
 * Outputs a fail image at a given size, or with a given transform.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image;
use Schoolbox\Application\BasicRequest;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new BasicRequest\Kernel();

$transformType = $_GET['transform_type'] ?? '';
$browserCacheTimeoutConfigName = $transformType === 'portrait'
    ? ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT
    : ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT;

// Use the type of transform to determine the timeout
$kernel->outputCacheControlMaxAgeHeaderFromConfig($browserCacheTimeoutConfigName);

//
// Get the code, then the image path from the code.
$code = 500;
switch ($_GET['code']) {
    case 403:
    case 404:
    case 500:
    case 503:
    case 504:
        $code = (int)$_GET['code'];
        break;

    default:
        break;
}
switch ($code) {
    case 403:
        $path = '/images/placeholders/permission-denied.png';
        $hash = '27e7f8e7e63a8b2562128842fc3302f7758647be';
        break;

    case 404:
    case 500:
    default:
        $path = '/images/placeholders/corrupt-image.png';
        $hash = '93c3e6fc5c928ef24b65ee41a03c5756d8e1c09d';
        break;
}


//
// Build the transformation.
// If there's no valid transformation, then transform to a given size.
switch ($transformType) {
    case Image\Transformation\Portrait::getSlug():
        $path       = '/images/placeholders/portrait.png';
        $hash       = '4ffb9e38f95e5fbafe0277c6a424e2b47b38d997';
        $transforms = new Image\Transformation\Portrait();
        $size       = $_GET['transform'] ?? '';
        try {
            $transform = $transforms->retrieve($size);
            $cache_key = $transforms->getCacheKey($size);
        } catch (\OutOfBoundsException $e) {
            $transform = $transforms->retrieve('profile');
            $cache_key = $transforms->getCacheKey('profile');
        }
        break;

    case Image\Transformation\Storage::getSlug():
        $transforms = new Image\Transformation\Storage();
        $size       = $_GET['transform'] ?? '';
        try {
            $transform = $transforms->retrieve($size);
            $cache_key = $transforms->getCacheKey($size);
        } catch (\OutOfBoundsException $e) {
            $transform = $transforms->retrieve('maxsize');
            $cache_key = $transforms->getCacheKey('maxsize');
        }
        break;

    case Image\Transformation\MyImage::getSlug():
    default:
        $width      = (int)$_GET['width'] ?: 100;
        $height     = (int)$_GET['height'] ?: 100;
        $transforms = new Image\Transformation\MyImage();
        $transform  = $transforms->retrieve($width, $height);
        $cache_key  = $transforms->getCacheKey($width, $height);
        break;
}

// If transient (503), otherwise likely a 200, regardless if a 4/5xx, to enable caching of broken images
if (in_array($code, [503, 504], true)) {
    http_response_code($code);
}
/** @var IConfigLoader $config */
$config = $kernel->get('config');
// Try to transform the fail image in /resources
// If even THAT fails, just redirect to the static version.
// (The redirect should never happen anymore, as an invalid transform falls back
//  to a valid one now.)
try {
    if (
        $config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3 &&
        $config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)
    ) {
        $preSignedUrlService    = new PreSignedUrlService($config);
        $storage                = (new Storage\Model())->setHash($hash);
        $preSignedUrlDataObject = $preSignedUrlService->generate(
            $storage,
            Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds($config->get($browserCacheTimeoutConfigName)),
            $transforms->getTranscodePath(Storage\Manager::getPathFromHash($hash), $cache_key),
            ['private', 'must-revalidate'],
            PreSignedUrlService::DISPOSITION_INLINE,
            'image/png',
            basename($path)
        );
        $preSignedUrlService->download($preSignedUrlDataObject);
    }

    /** @var FilesystemProxy $filesystemProxy */
    $filesystemProxy = $kernel->get('filesystem.storageProxy');
    $imageProcessor  = new Image\Processor(
        Cache\LocalFS::getStorageModel(WEB_root, '/static' . $path),
        $filesystemProxy,
        $cache_key,
        $transform
    );
    $imageProcessor->load()->stream();
} catch (Image\Exception\ConcurrentTransformationException|InvalidPresignedUrlDurationExecption $e) {
    $kernel->outputResourceBeingProcessedHeader();
    $prefixGenerator = $kernel->get('cdnPrefixGenerator');
    $prefixGenerator->redirectTemporary($path);
} catch (Exception $e) {
    /** @var PrefixGenerator $prefixGenerator */
    $prefixGenerator = $kernel->get('cdnPrefixGenerator');
    $prefixGenerator->redirectTemporary($path);
}
