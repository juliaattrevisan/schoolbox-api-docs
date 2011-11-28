<?php

session_start();

require_once dirname(__FILE__) . '/../include/SitePage.php';
require_once SITE_root . '/library/DeprecatedUser.php';

$page = new SitePage( false );
$userHandler = new DeprecatedUser;

$recaptcha = new ReCaptcha( Config::get('recaptcha_public_api_key'), Config::get('recaptcha_private_api_key') );

$page->setFile(TEMPLATE_content, 'feedback.html');
$page->setBlock(TEMPLATE_content, 'recaptcha', 'recaptcha_block');
$page->setTitle('Feedback');

if ( is_numeric($_SESSION['id']) ) {
    $user = $userHandler->getByID($_SESSION['id']);

    $page->setVar('recaptcha_block', '');
}
else {
    $page->setVar('RECAPTCHA', $recaptcha->form());
    $page->parse('recaptcha_block', 'recaptcha');
}

$email = new Email();

$output = array(
    'NAME' => isset( $user ) ? $email->formatEmailFromArray($user) : '',
    'EMAIL' => isset( $user ) ? $email->formatEmailFromArray($user) : '',
);

$page->setVar($output);

$page->buildPage();
$page->outputPage(true);

?>