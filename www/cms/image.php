<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

/* Preserve old checks
 */
$page = new SitePage();

if ($_GET['id'] && $_GET['width'] && $_GET['height']) {
    $db = DeprecatedDatabase::getInstance();

	$query = "SELECT id, mime_type, category, deleted FROM file WHERE id = {$_GET['id']}";
	$fileDir = Cfg::get('file_dir');

	$imageInfo = $db->getRow($query);

	/* See if you can view this image
	 */
	if (isset($imageInfo['category'])) {
		$groupHandler = new Group();

		if ($groupHandler->getPermission($imageInfo['category']) < READ_ACCESS) {
			error(5);
		}
	}
	else {
		error(5);
	}
}

/* Choose a caching method to use
 */
$key = $_GET['width'] . 'x' . $_GET['height'];

$cache = new Alaress_Image_Cache_Image($_GET['id'], $key);

/* Process and stream image
 */
$imageProcessor = new Alaress_Image_Processor_Freeform($cache, new Alaress_Image_Transformation_MyImage($_GET['width'], $_GET['height']));
$imageProcessor->load()->transformAll()->stream();