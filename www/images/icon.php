<?php
/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__.'/../../include/Bootstrap.php';

$path = '/images/core/icons/activity-icons-72x72px.png';

$icon = isset($_GET['icon']) ? $_GET['icon'] : '';
$size = isset($_GET['size']) ? $_GET['size'] : 64;

// Try to output the fail image. If even THAT fails, just redirect to the image.
try {
    $transform = new \Alaress\Image\Transformation\Icon($icon, $size);
    $transform_key = "Icon";
    $cache_key = "$icon-$size";

    $imageProcessor = new Alaress_Image_Processor_Freeform(
        new Alaress_Image_Cache_Filesystem(WEB_root.$path, $cache_key),
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
