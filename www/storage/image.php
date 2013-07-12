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
		/* @var $resource Schoolbox\File */
		$resource = Schoolbox\FileMapper::getInstance()->findOneByColumn('hash', $_GET['hash']);
		if ($resource != false) {
			$image = new Alaress_Image_Storage($resource, $_GET['size'], new Alaress_Image_Transformation_Storage());
			exit;
		}
	}
}
catch (Exception $e) {}
SitePage::redirectToBrokenImageWithTransform('storage', $_GET['size'], 404);
