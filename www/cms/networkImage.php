<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Transformation\MyImage;
use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

[$width, $height] = MyImage::ensureDimensionsAreReasonable($_GET['width'] ?? null, $_GET['height'] ?? null);

// a bit heavy-handed, but I guess we're not implementing file previews for network??
SitePage::redirectToBrokenImageOfSize($width, $height, 404);
