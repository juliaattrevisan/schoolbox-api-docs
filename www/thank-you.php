<?php

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

session_start();

$page = new SitePage(false);

$page->setFile(TEMPLATE_content, 'thank-you.html');
$page->setTitle('Message Delivered');

$page->buildPage();
$page->outputPage(true);

?>