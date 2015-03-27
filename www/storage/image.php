<?php
/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once '../../include/Bootstrap.php';

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);

        $image = new Alaress\Image\Storage(
            $resource,
            $_GET['size'],
            new \Alaress\Image\Transformation\Storage()
        );

        $image->stream();
        exit;
    }
}
catch (Exception $e) { }
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
