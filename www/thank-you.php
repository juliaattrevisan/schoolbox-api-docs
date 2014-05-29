<?php

session_start();

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

$page = new SitePage(false);

$page->setFile(TEMPLATE_content, 'thank-you.html');
$page->setTitle('Message Delivered');

$page->buildPage();
$page->outputPage(true);

?>