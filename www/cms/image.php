<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

/* Preserve old checks
 */
session_start();

/* Choose a caching method to use
 */
$key   = $_GET['width'] . 'x' . $_GET['height'];

try {
    $file = Schoolbox\Resource\File\Mapper::getInstance()->findById($_GET['id']);
    /* See if you can view this image
     */
    $groupHandler = new Group();

    if ($groupHandler->getPermission($file->getFolder()->getId()) < READ_ACCESS) {
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
}
catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 500);
}
