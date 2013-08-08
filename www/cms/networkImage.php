<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);

if ($_GET['id'] && $_GET['width'] && $_GET['height']) {
	if (empty($_SESSION['id'])) {
		Sitepage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 403);
	}

	$key = $_GET['width'] . 'x' . $_GET['height'];

	$cache = new Alaress_Image_Cache_MyImage('network-' . $_GET['id'], $key);

	$fileHandler = PortfolioWebDAV::factory();
	try {
		$filename = $fileHandler->getFile($_GET['id']);
	}
	catch (Sabre\DAV\Exception $ex) {
		error(41);
	}

}