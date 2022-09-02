<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Attachment\Factory as ResourceAttachmentFactory;
use Schoolbox\Resource\Attachment\Context\Factory as ResourceContextFactory;
use Schoolbox\User;

if (empty($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/resources/attachment');
}

/** @var SitePage $this */

$this->setAjax();

$this->setFile(TEMPLATE_content, '/resources/attach-browser.html');
$this->setBlock(TEMPLATE_content, 'source', 'source_block');
$this->setBlock(TEMPLATE_content, 'source_panel', 'source_panel_block');

$multi             = !empty($_GET['multi']);
$filesystemContext = empty($_GET['fs']) ? false : $_GET['fs'];
$defaultFolderId   = (int)($_GET['fid'] ?? 0);
$expiryDate        = $_GET['expiryDate'] ?? null;

/* This is the first instance of two places where this is specified
 * The other is www/ajax/resources.php
 */
if (!isset($_GET['mime'])) {
    $mimeFilter = null;
} elseif ('' === $_GET['mime']) {
    $mimeFilter = false;
} else {
    $mimeFilter = urldecode($_GET['mime']);
}

/* Whether the Use This Folder button should be available
 */
$useFolder = isset($_GET['f']);

/* Figger out which filesystem options to show
 */
$context = new ResourceContextFactory($filesystemContext);
$filteredOptions = $context->produce();

// register our known defaults
$factory = new ResourceAttachmentFactory($filteredOptions);

/** @var IConfigLoader $configLoader */
$configLoader = $this->getDi()->get('config');

/** @var RosettaStone $rosettaStone */
$rosettaStone = $this->getDi()->get('l10n');

/* This loads the set of accordions in the modal - it's basically the outer shell
 * Each of the accordions asynchronously calls www/ajax/resources.php, loading that
 * specific accordion's contents
 */
$factory->allowMulti($multi)
        ->setMimeFilter($mimeFilter)
        ->setConfig($configLoader)
        ->setRosetta($rosettaStone)
        ->setUseFolder($useFolder)
        ->setDefaultFolderId($defaultFolderId)
        ->setExpiryDate(
            isset($expiryDate)
                ? new Alaress_Date_Format($expiryDate, DateTimeInterface::RFC3339)
                : null
        );
if ($_SESSION['user'] instanceof User) {
    $factory->setUser($_SESSION['user']);
}

foreach ($factory->getMappedControllers() as $controller) {
    /* Render out the attachment option
     */
    $this->setVar([
        'SRC_SLUG'   => str_replace('/', '-', $controller->getSlug()),
        'SRC_ICON'   => $controller->getIcon(),
        'SRC_LABEL'  => $controller->getLabel(),
        'SRC_URL'    => $controller->getUrl(),
        'SRC_METHOD' => $controller->getRenderMethod()
    ]);

    $this->parse('source_block', 'source', true);

    /* Render out the attachment option's corresponding panel
     */
    $this->parse('source_panel_block', 'source_panel', true);
}

$this->injectGlobalVars();
$this->template->pparse('finaloutput', TEMPLATE_content);
