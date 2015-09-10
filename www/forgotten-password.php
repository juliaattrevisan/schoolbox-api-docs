<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage(false);

$page->setFile(TEMPLATE_body, 'common/forgotten-password.html');
$page->setTitle('Forgotten Password');
$page->setBlock(TEMPLATE_body, 'email_sent', 'email_sent_block');
$page->setBlock(TEMPLATE_body, 'not_found', 'not_found_block');
$page->setBlock(TEMPLATE_body, 'parents_only', 'parents_only_block');

$page->setVar('email_sent_block', '');
$page->setVar('not_found_block', '');
$page->setVar('parents_only_block', '');

if (isset($_POST['email'])) {
	
	$userHandler = new User;
	
	$result = $userHandler->sendPassword($_POST['email']);
	
	if ($result == 1) {
	    $page->parse('email_sent_block', 'email_sent');
	}
	else if ($result == 0) {
	    $page->parse('not_found_block', 'not_found');
	}
	else if ($result == 2) {
        $page->parse('parents_only_block', 'parents_only');
	}
}

$page->outputPage(true);

?>
