<?php

use Alaress\Application\Kernel;

/** @var Kernel $kernel */
$kernel = require_once '../library/Bootstrap.php';

/** @var SitePage $page */
$page = $kernel->getDependencyInjector()->get('layoutEngine');
$page->setSite();
$page->setFile(TEMPLATE_content, 'policy/view.html');
$page->setTitle('Guidelines of Use and Privacy Policy');

$page->setVar('POLICY', Cfg::get('policy'));

$page->outputPage();
