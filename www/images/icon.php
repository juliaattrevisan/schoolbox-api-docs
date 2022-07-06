<?php

/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\ProcessorFactory;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImage();
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

$path = '/static/images/icons/activity-icons-64x64px.png';

$icon = isset($_GET['icon']) ? $_GET['icon'] : '';
$size = isset($_GET['size']) ? $_GET['size'] : 32;

// handle "square32" as well as just `32` then cast to int, to confirm it is in the correct format
$size = (int)str_replace('square', '', $size);

// Try to output the fail image. If even THAT fails, just redirect to the image.
try {
    $iconTransform   = new Image\Transformation\Icon();
    $transform       = $iconTransform->retrieve($icon, $size);
    $cache_key       = $iconTransform->getCacheKey($icon, $size);

    /** @var ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    $imageProcessor   = $processorFactory->buildForLocalFile(
        WEB_root,
        $path,
        $cache_key,
        $transform,
    );
    $imageProcessor->load()->stream();
} catch (ConcurrentTransformationException $e) {
    $kernel->outputResourceBeingProcessedHeader();
    SitePage::redirectToBrokenImage($e->getHttpStatusCode());
} catch (Exception $e) {
//  echo $e;
    SitePage::redirectToBrokenImage();
}
