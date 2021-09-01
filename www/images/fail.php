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
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// Use the type of transform to determine the timeout
$kernel->outputCacheControlMaxAgeHeaderFromConfig(
    $_GET['transform_type'] === 'portrait'
        ? ConfigMapper::NAME_BROWSER_CACHE_PORTRAIT_TIMEOUT
        : ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT
);

//
// Get the code, then the image path from the code.
$code = 500;
switch ($_GET['code']) {
    case 403:
    case 404:
    case 500:
    case 503:
        $code = $_GET['code'];
        break;

    default:
        break;
}
$path = null;
switch ($code) {
    case 403:
        $path = '/images/placeholders/permission-denied.png';
        break;

    case 404:
    case 500:
    default:
        $path = '/images/placeholders/corrupt-image.png';
        break;
}


//
// Build the transformation.
// If there's no valid transformation, then transform to a given size.
$transform = null;
$cache_key = null;
switch ($_GET['transform_type']) {
    case Image\Transformation\Portrait::getSlug():
        $path       = '/images/placeholders/portrait.png';
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
        $width     = (int)$_GET['width'] ?: 100;
        $height    = (int)$_GET['height'] ?: 100;
        $myImage   = new Image\Transformation\MyImage();
        $transform = $myImage->retrieve($width, $height);
        $cache_key = $myImage->getCacheKey($width, $height);
        break;
}

// If transient (503), otherwise likely a 200, regardless if a 4/5xx, to enable caching of broken images
if ($code === 503) {
    http_response_code($code);
}
// Try to transform the fail image in /resources
// If even THAT fails, just redirect to the static version.
// (The redirect should never happen anymore, as an invalid transform falls back
//  to a valid one now.)
try {
    $imageProcessor = new Image\Processor(
        Cache\LocalFS::getStorageModel(WEB_root, '/static' . $path),
        $cache_key,
        $transform
    );
    $imageProcessor->load()->stream();
} catch (Image\Exception\ConcurrentTransformationException $e) {
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::localRedir($path, false, $e->getHttpStatusCode());
} catch (Exception $e) {
    /** @var PrefixGenerator $prefixGenerator */
    $prefixGenerator = $kernel->get('cdnPrefixGenerator');
    $prefixGenerator->redirectTemporary($path);
}
