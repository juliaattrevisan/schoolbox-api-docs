<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

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
$location = $this->arg[0];
$source   = $this->arg[1];
$multi    = $this->arg[2] == 'multi';
$mime     = $_GET['mime'];

$this->setVar([
    'SITE_VERSION'  => Cfg::get('site_version'),
    'SITE_LOCATION' => $this->site,
]);

$factory = new Schoolbox\Resource\Attachment\Factory();

// TODO use DI version
$factory->setSource($location, $source)
        ->setMimeFilter($mime)
        ->allowMulti($multi)
        ->setConfig($this->getDi()->get('config'));

$factory->build()->parse($this);
