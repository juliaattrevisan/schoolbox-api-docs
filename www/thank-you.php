<?php

/** @var Alaress\Application\Kernel $kernel */
$kernel = require_once dirname(__FILE__) . '/../library/Bootstrap.php';

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

    /**
     * Support and Feedback custom link
     */
    $config = new \Schoolbox\Config\Loader();
    $page->setBlock(TEMPLATE_body, 'feedback_link', 'feedback_link_block');

    $feedbackText = $config->get('feedback_text');
    $feedbackURL = $config->get('feedback_url');

    if (strlen($feedbackText) && strlen($feedbackURL)) {
        $page->parse('feedback_link_block', 'feedback_link');
    } else {
        $page->setVar('feedback_link_block', '');
    }

    $page->setLite(true);
    $page->outputPage(true);
}
