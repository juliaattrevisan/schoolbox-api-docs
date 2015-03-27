<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
use Alaress\Image\Cache;
use Alaress\Image\Implementation\Imagine;
use Alaress\Image\Processor\Freeform;
use Alaress\Image\Transformation;

require_once __DIR__ . '/../../include/Bootstrap.php';

/* Preserve old checks
 */
session_start();

$fileDir = Cfg::get('file_dir');

if ($_GET['id'] && $_GET['width'] && $_GET['height']) {
    $db = DeprecatedDatabase::getInstance();

    $query = "SELECT id, mime_type, category, deleted FROM file WHERE id = {$db->smartQuote($_GET['id'])}";

    $imageInfo = $db->getRow($query);

    /* See if you can view this image
	 */
    if (isset($imageInfo['category'])) {
        $groupHandler = new Group();

        if ($groupHandler->getPermission($imageInfo['category']) < READ_ACCESS) {
            SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 403);
        }
    } else {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 403);
    }
}

/* Choose a caching method to use
 */
$key   = $_GET['width'] . 'x' . $_GET['height'];
$file  = Schoolbox\Resource\File\Mapper::getInstance()->findById($_GET['id']);
$store = $file->getStorage();

/* Process and stream image
 */
try {
    $imageProcessor = new Freeform(
        new Cache\Storage($store, $key),
        new Transformation\MyImage($_GET['width'], $_GET['height']),
        new Imagine\Filetype\Factory(),
        new Imagine\Processor\Strategy\Factory()
    );
    $imageProcessor->load()->stream();
} catch (Exception_Schoolbox_NotFound $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
}
catch (Exception $e) {
    SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 500);
}
