<?php
/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\ConfigMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);

        $size = $_GET['size'] ?: 'maxsize';

        $image = new Alaress\Image\Storage(
            $resource,
            $size,
            new \Alaress\Image\Transformation\Storage()
        );

        $image->stream();
        exit;
    }
} catch (Exception $e) {
    // fall through
}
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
