<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage(false);
$userHandler = new User;

$page->setFile(TEMPLATE_body, 'common/reset.html');
$page->setBlock(TEMPLATE_body, 'check_email', 'check_email_block');
$page->setBlock(TEMPLATE_body, 'reset_failed', 'reset_failed_block');

$page->setVar('check_email_block', '');
$page->setVar('reset_failed_block', '');

if ($userHandler->resetPassword($_GET['id'], $_GET['h']))
	$page->parse('check_email_block', 'check_email');
else
	$page->parse('reset_failed_block', 'reset_failed');

$page->outputPage(true);

?>
