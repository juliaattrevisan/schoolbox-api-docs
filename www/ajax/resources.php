<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IConfigLoader;
use Schoolbox\User;

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

/* This is the second instance of two places where this is specified
 * The other is www/resources/attachment.php
 */
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
$defaultFolderId = (int)($_GET['fid'] ?? 0);
$expiryDate = $_GET['expiryDate'] ?? null;

$this->setVar([
    'SITE_VERSION'  => Config::get('site_version'),
    'SITE_SCHOOL_CODE' => Config::get('site_school_code'),
]);

/** @var IConfigLoader $configLoader */
$configLoader = $this->getDi()->get('config');

/* Parse individual template in the accordion
 * This is the inner shell of each accordion which loads when you expand an accordion
 */
$factory = new Schoolbox\Resource\Attachment\Factory();
$factory
    ->setMimeFilter($mimeFilter)
    ->allowMulti($multi)
    ->setConfig($configLoader)
    ->setUseFolder($useFolder)
    ->setDefaultFolderId($defaultFolderId)
    ->setExpiryDate(
        isset($expiryDate)
            ? new Alaress_Date_Format($expiryDate, DateTimeInterface::RFC3339)
            : null,
    );
if ($_SESSION['user'] instanceof User) {
    $factory->setUser($_SESSION['user']);
}

$factory
    ->build($source)
    ->parse($this);
