<?php
/**
 * Outputs a fail image at a given size, or with a given transform.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../include/Bootstrap.php';

//
// Get the code, then the image path from the code.
$code = 500;
switch ($_GET['code']) {
	case 403:
	case 404:
	case 500:
		$code = $_GET['code'];
		break;

	default:
		break;
}
$path = null;
if ($_GET['portrait']) {
	$path = '/images/core/placeholder.png';
}
else {
	switch ($code) {
		case 403:
			$path = '/images/core/permission-denied.png';
			break;

		case 404:
		case 500:
		default:
			$path = '/images/core/corrupt-image.png';
			break;
	}
}


//
// Build the transformation.
// If there's no valid transformation, then transform to a given size.
$transform     = null;
$transform_key = null;
$cache_key     = '';
switch ($_GET['transform_type']) {
	case 'portrait':
		$transform     = new Alaress_Image_Transformation_Portrait();
		$transform_key = $_GET['transform'];
		$cache_key     = "portrait:$transform_key";
		break;

	case 'storage':
		$transform     = new Alaress_Image_Transformation_Storage();
		$transform_key = $_GET['transform'];
		$cache_key     = "storage:$transform_key";
		break;

	default:
		$width     = intval($_GET['width'])  ?: 100;
		$height    = intval($_GET['height']) ?: 100;
		$transform = new Alaress_Image_Transformation_MyImage($width, $height);
		$cache_key = "{$width}x{$height}";
		break;
}

//
// Try to output the fail image. If even THAT fails, just redirect to the image.
try {
	$imageProcessor = new Alaress_Image_Processor_Freeform(
		new Alaress_Image_Cache_Filesystem(WEB_root.$path, $cache_key),
		$transform
	);
	if (isset($transform_key)) {
		$imageProcessor->setTransformKey($transform_key);
	}
	$imageProcessor->load()->transformAll()->stream();
}
catch (Exception $e) {
	SitePage::localRedir($path);
}
