<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage();
$page->setLite(true);

$page->setFile(TEMPLATE_content, '/component/homepage/socialstream/video.html');

$page->setVar(array(
	'SOCIALSTREAM_STORAGE_HASH' => $_GET['hash'],
));

$page->template->pparse('finaloutput', TEMPLATE_content);
