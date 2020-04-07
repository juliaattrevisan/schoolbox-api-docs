<?php
/**
 * Outputs a fail image at a given size, or with a given transform.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
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
        $path = '/images/core/permission-denied.png';
        break;

    case 404:
    case 500:
    default:
        $path = '/images/core/corrupt-image.png';
        break;
}


//
// Build the transformation.
// If there's no valid transformation, then transform to a given size.
$transform     = null;
$transform_key = null;
$cache_key     = '';
switch ($_GET['transform_type']) {
    case 'portrait':
        $path          = '/images/core/placeholder.png';
        $transform     = new \Alaress\Image\Transformation\Portrait();
        $transform_key = $_GET['transform'];
        try {
            $transform->retrieve($transform_key);
        } catch (\OutOfBoundsException $e) {
            $transform_key = 'profile';
        }
        $cache_key = "portrait-$transform_key";
        break;

    case 'storage':
        $transform     = new \Alaress\Image\Transformation\Storage();
        $transform_key = $_GET['transform'];
        try {
            $transform->retrieve($transform_key);
        } catch (\OutOfBoundsException $e) {
            $transform_key = 'maxsize';
        }
        $cache_key = "storage-$transform_key";
        break;

    default:
        $width     = intval($_GET['width']) ?: 100;
        $height    = intval($_GET['height']) ?: 100;
        $transform = new \Alaress\Image\Transformation\MyImage($width, $height);
        $cache_key = "{$width}x{$height}";
        break;
}

// Try to output the fail image. If even THAT fails, just redirect to the image.
try {
    $imageProcessor = new Freeform(
        new Cache\BrokenFS(WEB_root, $path, $cache_key),
        $transform,
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    if (isset($transform_key)) {
        $imageProcessor->setTransformKey($transform_key);
    }
    $imageProcessor->load()->stream();
} catch (Exception $e) {
    SitePage::localRedir($path);
}
