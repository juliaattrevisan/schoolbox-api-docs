<?php

use Alaress\Application\Kernel;

/** @var Kernel $kernel */
$kernel = require_once '../library/Bootstrap.php';


// /policy.php? redirect to /policy
SitePage::redirectToPageWithoutPhpExtension();


/** @var SitePage $this */
$this->setSite();
$this->setFile(TEMPLATE_content, 'policy/view.html');
$this->setTitle('Guidelines of Use and Privacy Policy');

$this->setVar('POLICY', Scrub::html($this->getDi()->get('config')->get('policy')));

$this->outputPage();
