<?php
require_once('../include/Bootstrap.php');

$page = new SitePage(false);
$page->setSite();
$page->setFile(TEMPLATE_body, 'policy/view.html');
$page->setTitle('Guidelines of Use and Privacy Policy');

$page->setVar(array(
	'POLICY' => Cfg::get('policy')
));

$page->outputPage(true);