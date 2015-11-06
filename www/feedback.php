<?php
/** @var Alaress\Application\Kernel $kernel */
$kernel = require_once dirname(__FILE__) . '/../include/Bootstrap.php';

session_start();

if (isset($this)) {
    $page = $this;
} else {
    /** @var SitePage $page */
    $page = new SitePage(false);
    $page->setDi($kernel->getDependencyInjector());
}

if (is_numeric($_SESSION['id'])) {
    $userHandler = new User;
    $user = $userHandler->getByID($_SESSION['id']);

    $page->setFile(TEMPLATE_content, 'feedback/authenticated.html');
    $page->setBlock(TEMPLATE_content, 'recaptcha', 'recaptcha_block');

    $email = new Email();

    $page->setVar(array(
        'TITLE' => Cfg::get('title'),
        'NAME'  => $userHandler->formatName($user),
        'EMAIL' => $email->formatEmailFromArray($user),
    ));

    $page->setVar('recaptcha_block', '');

    $page->outputPage();
}
else {
    $recaptcha = new ReCaptcha(Config::get('recaptcha_public_api_key'), Config::get('recaptcha_private_api_key'));

    $page->setFile(TEMPLATE_body, 'feedback/unauthenticated.html');
    $page->setBlock(TEMPLATE_body, 'recaptcha', 'recaptcha_block');

    $page->setVar(array(
        'TITLE' => Cfg::get('title'),
        'NAME'  => '',
        'EMAIL' => '',
        'RECAPTCHA' => $recaptcha->form(),
    ));

    $page->parse('recaptcha_block', 'recaptcha');

    $page->setLite(true);
    $page->outputPage(true);
}

