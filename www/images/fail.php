<?php
/**
 * Outputs a fail image at a given size.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../include/Bootstrap.php';
session_start();

//
// permission denied, corrupt, portrait only
$code   = 500;
$width  = intval($_GET['width'])  ?: 100;
$height = intval($_GET['height']) ?: 100;

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
	$path = WEB_root . '/images/core/placeholder.png';
}
else {
	switch ($code) {
		case 403:
			$path = WEB_root . '/images/core/permission-denied.png';
			break;
		case 404:
		case 500:
		default:
			$path = WEB_root . '/images/core/corrupt-image.png';
			break;
	}
}

$imageProcessor = new Alaress_Image_Processor_Freeform(
	new Alaress_Image_Cache_Filesystem($path, "{$width}x{$height}"),
	new Alaress_Image_Transformation_MyImage($width, $height)
);
$imageProcessor->load()->transformAll()->stream();