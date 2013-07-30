<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../../../../include/Bootstrap.php');

$page = new SitePage();
$page->template->set_file(TEMPLATE_body, dirname(__FILE__) . '/file_uploader.html');
$page->setBlock(TEMPLATE_body, 'error', 'error_block');

//
// Was there an error? If so, get it from somewhere and reset the page.
if (isset($_GET['error'])) {
	$error_message = 'The file could not be uploaded.';
	switch ($_GET['error']) {
		case Schoolbox\File\Exception::CODE_MIMETYPE_NOT_FOUND:
			$error_message = 'The file could not be uploaded - its file type is not recognised.';
			break;
		case Schoolbox\File\Exception::CODE_FILE_TOO_LARGE:
			$error_message = 'The file could not be uploaded - it was too large.';
			break;
		default:
			break;
	}
	$page->setVar('ERROR_MESSAGE', $error_message);
	$page->parse('error_block', 'error');
}
else {
	$page->setVar('error_block', '');
}

$output = array(
	'REQUEST_URI' => $_SERVER['REQUEST_URI'],
);
$page->setVar($output);

$page->outputPage(true);