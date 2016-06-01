<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../library/Bootstrap.php';
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

$factory = new Schoolbox\Resource\Attachment\Factory();

$factory->setSource($location, $source)
        ->setMimeFilter($mime)
        ->allowMulti($multi)
        ->setConfig(new \Schoolbox\Config\Loader());

$factory->build()->parse($this);
