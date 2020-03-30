<?php
/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Cache\BrokenFS;
use Alaress\Image\Processor\Freeform;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setCacheMaxAgeFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

$path = '/images/core/icons/activity-icons-64x64px.png';

$icon = isset($_GET['icon']) ? $_GET['icon'] : '';
$size = isset($_GET['size']) ? $_GET['size'] : 32;

// handle "square32" as well as just `32`
$size = str_replace('square', '', $size);

// Try to output the fail image. If even THAT fails, just redirect to the image.
try {
    $transform = new \Alaress\Image\Transformation\Icon($icon, $size);
    $transform_key = "Icon";
    $cache_key = "$icon-$size";

    $imageProcessor = new Freeform(
        new BrokenFS(WEB_root, $path, $cache_key),
        $transform,
        new \Alaress\Image\Implementation\Imagine\Filetype\Factory(),
        new \Alaress\Image\Implementation\Imagine\Processor\Strategy\Factory()
    );

    if (isset($transform_key)) {
        $imageProcessor->setTransformKey($transform_key);
    }

    $imageProcessor->load()->stream();
} catch (Exception $e) {
//	echo $e;
    SitePage::redirectToBrokenImage();
}
