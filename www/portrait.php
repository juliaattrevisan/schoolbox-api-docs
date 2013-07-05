<?php
/**
 * Optimus Prime
 * Autobots, transform and roll out!
 *
 * Example usages of Alaress_Image_Processor
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once '../include/Bootstrap.php';

//
// Alaress_Image_Cache_Portrait needs to know if someone is logged in.
session_start();

try {
	$image = new Alaress_Image_Portrait($_GET['userId'], $_GET['size'], new Alaress_Image_Transformation_Portrait());
	$image->stream();
}
catch (Exception_Schoolbox_AccessDenied $e) {
	SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 403);
}
catch (Exception_Schoolbox_NotFound $e) {
	SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 404);
}
catch (Exception $e) {
	SitePage::redirectToBrokenImageWithTransform('portrait', $_GET['size'], 500);
}