<?php
/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

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
}
catch (Exception $e) { }
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
