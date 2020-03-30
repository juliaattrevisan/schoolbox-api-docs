<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;
use Schoolbox\Application\BasicRequest\Kernel;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

header('Cache-Control: max-age=10800, private');

/* Preserve old checks
 */
session_start();

/* Choose a caching method to use
 */
$key = $_GET['width'] . 'x' . $_GET['height'];

try {
    $file = Schoolbox\Resource\File\Mapper::getInstance()->findById($_GET['id']);
    /* See if you can view this image
     */
    $groupHandler = new Group();

    if ($groupHandler->getPermission($file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 403);
    }

} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
}

/* Process and stream image
 */
try {
    /** @var Schoolbox\Resource\File\Model $file */
    /** @var Schoolbox\Resource\Storage\Model $store */
    $store = $file->getStorage();

    $imageProcessor = new Freeform(
        new Cache\Storage($store, $key),
        new Transformation\MyImage($_GET['width'], $_GET['height']),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $imageProcessor->load()->stream();
} catch (SchoolboxNotFoundException $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
} catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 500);
}
