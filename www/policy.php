<?php
require_once('../library/Bootstrap.php');

$page = new SitePage(false);
$page->setSite();
$page->setFile(TEMPLATE_body, 'policy/view.html');
$page->setTitle('Guidelines of Use and Privacy Policy');

$page->setVar(array(
	'POLICY' => Cfg::get('policy')
));

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

$page->outputPage(true);
