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

	$query = "SELECT id, owner, mime_type, deleted FROM myFile WHERE id = {$_GET['id']}";
	$fileDir = Cfg::get('myfile_dir');

	$imageInfo = $db->getRow($query);

	/* TODO: Add permissions later to prevent people from manufacturing
	 * links to other people's images
	 */
//	/* See if you can view this image
//	 */
//	$eportfolioHandler = new Eportfolio_Container($imageInfo['owner']);
//
//	if (!$eportfolioHandler->canEdit($imageInfo['owner'])) {
//		error(5);
//	}

	/* Choose a caching method to use
	 */
	$key = $_GET['width'] . 'x' . $_GET['height'];

	$cache = new Alaress_Image_Cache_MyImage($_GET['id'], $key);

	/* Process and stream image
	 */
	$imageProcessor = new Alaress_Image_Processor_Freeform($cache, new Alaress_Image_Transformation_MyImage($_GET['width'], $_GET['height']));
	$imageProcessor->load()->transformAll()->stream();
}
else {
	$cache = new Alaress_Image_Cache_MyImage($_GET['id'], $_GET['key']);

	/* Process and stream image
	 */
	$imageProcessor = new Alaress_Image_Processor_Standard($cache, new Alaress_Image_Transformation_Storage());
	$imageProcessor->load()->transformAll()->stream();
}