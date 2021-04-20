<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$page->setLite(true);

$page->setFile(TEMPLATE_content, '/component/homepage/socialstream/video.html');

$page->setVar(array(
	'SOCIALSTREAM_STORAGE_HASH' => $_GET['hash'],
));

$this->injectGlobalVars();
$page->template->pparse('finaloutput', TEMPLATE_content);
