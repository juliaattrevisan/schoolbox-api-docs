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
    case 'portrait':
        $path          = '/images/placeholders/portrait.png';
        $transforms     = new Image\Transformation\Portrait();
        $transform_key = $_GET['transform'];
        try {
            $transform = $transforms->retrieve($transform_key ?? '');
        } catch (\OutOfBoundsException $e) {
            $transform = $transforms->retrieve('profile');
        }
        $cache_key = "portrait-$transform_key";
        break;

    case 'storage':
        $transforms     = new Image\Transformation\Storage();
        $transform_key = $_GET['transform'];
        try {
            $transform = $transforms->retrieve($transform_key ?? '');
        } catch (\OutOfBoundsException $e) {
            $transform = $transforms->retrieve('maxsize');
        }
        $cache_key = "storage-$transform_key";
        break;

    default:
        $width     = intval($_GET['width']) ?: 100;
        $height    = intval($_GET['height']) ?: 100;
        $transform = (new Image\Transformation\MyImage())->retrieve($width, $height);
        $cache_key = "{$width}x{$height}";
        break;
}

// Try to transform the fail image in /resources
// If even THAT fails, just redirect to the static version.
// (The redirect should never happen anymore, as an invalid transform falls back
//  to a valid one now.)
try {
    $imageProcessor = new Image\Processor(
        Cache\LocalFS::getStorageModel(WEB_root, PrefixGenerator::LOCAL_PREFIX . $path),
        $cache_key,
        $transform
    );
    $imageProcessor->load()->stream();
} catch (Exception $e) {
    /** @var PrefixGenerator $prefixGenerator */
    $prefixGenerator = $kernel->get('cdnPrefixGenerator');
    $prefixGenerator->redirectTemporary($path);
}
