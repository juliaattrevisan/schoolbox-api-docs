<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$page = new SitePage(false);

$page->setFile(TEMPLATE_content, 'common/forgotten-password.html');
$page->setTitle('Forgotten Password');
$page->setBlock(TEMPLATE_content, 'email_sent', 'email_sent_block');
$page->setBlock(TEMPLATE_content, 'not_found', 'not_found_block');

$page->setVar('email_sent_block', '');
$page->setVar('not_found_block', '');

if (isset($_POST['email']) && isEmail($_POST['email'])) {
	
	$userHandler = new User;
	
	if ($userHandler->sendPassword($_POST['email']))
		$page->parse('email_sent_block', 'email_sent');
	else
		$page->parse('not_found_block', 'not_found');
}

$page->buildPage(false);
$page->outputPage(true);

?>