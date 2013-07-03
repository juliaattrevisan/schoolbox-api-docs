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
$width  = intval($_GET['width']);
$height = intval($_GET['height']);

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
switch ($code) {
	case 403:
		$path = __DIR__.'/permission-denied.png';
		break;
	case 404:
	case 500:
	default:
		$path = __DIR__.'/corrupt-image.png';
		break;
}

$imageProcessor = new Alaress_Image_Processor_Freeform(
	new Alaress_Image_Cache_Filesystem($path, "{$width}x{$height}"),
	new Alaress_Image_Transformation_MyImage($width, $height)
);
$imageProcessor->load()->transformAll()->stream();