<?php

session_start();

require_once dirname(__FILE__) . '/../include/SitePage.php';
require_once SITE_root . '/library/user.php';
//require_once SITE_root . '/library/reCaptcha.php';

$page = new SitePage(false);
$userHandler = new User;

$page->setFile(TEMPLATE_content, 'feedback.html');
$page->setBlock(TEMPLATE_content, 'recaptcha', 'recaptcha_block');
$page->setTitle('Feedback');

$page->setVar('recaptcha_block', '');
if (is_numeric($_SESSION['id']))
	$user = $userHandler->getByID($_SESSION['id']);
//else
	//$page->parse('recaptcha_block', 'recaptcha');

$email = new Email();

$output = array('NAME'	=> isset($user) ? $email->formatEmailFromArray($user) : '',
				'EMAIL'	=> isset($user) ? $email->formatEmailFromArray($user) : '',
				//'RECAPTCHA_IMAGE'		=> RECAPTCHA_SECURE_URL . '/image?c=' . ReCaptcha::getChallenge(),
				//'RECAPTCHA_CHALLENGE'	=> ReCaptcha::getChallenge(),
				);

$page->setVar($output);

$page->buildPage();
$page->outputPage(true);

?>