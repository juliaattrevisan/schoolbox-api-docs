<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

/* Preserve old checks
 */
session_start();

if (empty($_SESSION['id'])) {
    redirectToBroken(403);
}

try {
    $image = Schoolbox\Resource\My\File\Mapper::getInstance()->findById($_GET['id']);
} catch (Schoolbox\Mapper\Exception $e) {
    redirectToBroken(404);
    exit;
}

if (isset($_GET['key'])) {
    $cacheKey   = $_GET['key'];
    $transform = (new Image\Transformation\Storage())->retrieve($cacheKey);
} elseif (isset($_GET['width']) && isset($_GET['height'])) {
    $cacheKey   = $_GET['width'] . 'x' . $_GET['height'];
    $transform = (new Image\Transformation\MyImage())->retrieve((int)$_GET['width'], (int)$_GET['height']);
} else {
    redirectToBroken(400);
    exit;
}

/** @var Storage\Model $storageModel */
$storageModel = $image->getStorage();
$processor    = new Image\Processor($storageModel, $cacheKey, $transform);
try {
    $processor->load()->stream();
} catch (Image\Exception\ConcurrentTransformationException $e) {
    redirectToBroken($e->getHttpStatusCode());
} catch (SchoolboxNotFoundException $e) {
    redirectToBroken(404);
} catch (Exception $e) {
    redirectToBroken(500);
}

exit;

/**
 * @param int $code
 */
function redirectToBroken($code = 500)
{
    if (isset($_GET['key'])) {
        SitePage::redirectToBrokenImageWithTransform('storage', $_GET['key'], $code);
    } elseif (isset($_GET['width'])) {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height '] ?: $_GET['width'], $code);
    }
}
