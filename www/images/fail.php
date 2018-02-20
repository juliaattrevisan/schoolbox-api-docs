<?php
/**
 * Outputs a fail image at a given size, or with a given transform.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Implementation\Imagine;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

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
        $cache_key     = "portrait-$transform_key";
        break;

    case 'storage':
        $transform     = new \Alaress\Image\Transformation\Storage();
        $transform_key = $_GET['transform'];
        try {
            $transform->retrieve($transform_key);
        } catch (\OutOfBoundsException $e) {
            $transform_key = 'maxsize';
        }
        $cache_key     = "storage-$transform_key";
        break;

    default:
        $width     = intval($_GET['width'])  ?: 100;
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
