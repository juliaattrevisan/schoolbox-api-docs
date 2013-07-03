<?php
/**
 * Handles image resizing and caching on images from storage engine
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once '../../include/Bootstrap.php';
//
// Don't need to be logged in to view image.
$sitePage = new SitePage(false);

try {
	if (isset($_GET['hash']) && $_GET['hash']) {
		$resource = Storage_ResourceMapper::getInstance()->getByHash($_GET['hash']);
		if ($resource != false) {
			$image = new Alaress_Image_Storage($resource, $_GET['size'], new Alaress_Image_Transformation_Storage());
			exit;
		}
	}
}
catch (Exception $e) {}
Sitepage::redirectToBrokenImage(404);
