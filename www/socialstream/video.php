<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage();
$page->setLite(true);

$page->setFile(TEMPLATE_content, '/component/homepage/socialstreamVideo.html');

$page->setVar(array(
	'SOCIALSTREAM_STORAGE_HASH' => $_GET['hash'],
));

$page->template->pparse('finaloutput', TEMPLATE_content);