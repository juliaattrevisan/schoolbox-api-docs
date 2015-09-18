<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

session_start();

$page = new SitePage(false);

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
