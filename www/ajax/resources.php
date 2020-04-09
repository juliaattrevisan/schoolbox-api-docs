<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Schoolbox\IConfigLoader;

if (empty($this)) {
    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */
if (count($this->arg) < 3) {
    error(2);
    exit;
}

$source = $this->arg[1];
$multi  = $this->arg[2] === 'multi';

if (!isset($_GET['mime'])) {
    $mimeFilter = null;
} elseif ('' === $_GET['mime']) {
    $mimeFilter = false;
} else {
    $mimeFilter = urldecode($_GET['mime']);
}

/* Whether there is a Use This Folder button
 */
$useFolder = isset($_GET['f']);

$this->setVar([
    'SITE_VERSION'  => Config::get('site_version'),
    'SITE_LOCATION' => $this->site,
]);

/** @var IConfigLoader $configLoader */
$configLoader = $this->getDi()->get('config');

/* Parse individual template in the accordion
 */
(new Schoolbox\Resource\Attachment\Factory())
    ->setMimeFilter($mimeFilter)
    ->allowMulti($multi)
    ->setConfig($configLoader)
    ->setUseFolder($useFolder)
    ->build($source)
    ->parse($this);
