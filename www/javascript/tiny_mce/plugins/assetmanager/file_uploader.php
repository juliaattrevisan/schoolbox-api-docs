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
$page->setBlock(TEMPLATE_body, 'folder_list', 'folder_list_block');
$page->setBlock(TEMPLATE_body, 'file', 'file_block');

$output = array(
	'REQUEST_URI' => $_SERVER['REQUEST_URI'],
//	'REQUEST_URI' => '/storage/fetch.php',
);
$page->setVar($output);

$page->outputPage(true);