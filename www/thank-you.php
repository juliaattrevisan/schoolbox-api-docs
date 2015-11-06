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
    $page->setFile(TEMPLATE_content, 'feedback/thank-you-authenticated.html');
    $page->setTitle('Message Delivered');

    $page->outputPage();
}
else {
    $page->setFile(TEMPLATE_body, 'feedback/thank-you-unauthenticated.html');

    $page->setLite(true);
    $page->outputPage(true);
}
