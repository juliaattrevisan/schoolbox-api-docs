<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Resource\Attachment\Factory as ResourceAttachmentFactory;
use Schoolbox\Resource\Attachment\Context\Factory as ResourceContextFactory;

if (empty($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/resources/attachment');
}

/** @var SitePage $this */

$this->setAjax();

$this->setFile(TEMPLATE_content, '/resources/attach-browser.html');

$this->setBlock(TEMPLATE_content, 'source', 'source_block');

$multi            = empty($_GET['multi']) ? false : !!($_GET['multi']);
$mimeFilter       = empty($_GET['mime']) ? false : $_GET['mime'];
$filesystemContext = empty($_GET['fs']) ? false : $_GET['fs'];

/* Figger out which filesystem options to show
 */
$context = new ResourceContextFactory($filesystemContext);
$filteredOptions = $context->produce();

// register our known defaults
$factory = new ResourceAttachmentFactory($filteredOptions);

$factory->allowMulti($multi)
        ->setMimeFilter($mimeFilter)
        ->setConfig($this->getDi()->get('config'))
        ->setRosetta($this->getDi()->get('l10n'));

foreach ($factory->getMappedControllers() as $controller) {

    // the first controller found will be considered the 'default'
    if(!$this->getVar('DEFAULT_VIEW')) {
         $this->setVar('DEFAULT_VIEW', $controller->getSlug());
    }

    $this->setVar(array(
        'SRC_SLUG'   => str_replace('/', '-', $controller->getSlug()),
        'SRC_ICON'   => $controller->getIcon(),
        'SRC_LABEL'  => $controller->getLabel(),
        'SRC_URL'    => $controller->getUrl(),
        'SRC_METHOD' => $controller->getRenderMethod()
    ));
    $this->parse('source_block', 'source', true);
}

$this->template->pparse('finaloutput', TEMPLATE_content);
