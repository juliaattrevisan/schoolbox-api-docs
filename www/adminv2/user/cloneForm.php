<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Role;
use Schoolbox\RoleCloner;
use Schoolbox\RoleMapper;

if (isset($this)) {
    error(2);
}

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (!$_SESSION['su']) {
    error(19);
}

$clone = $_POST['clone'];

/** @var Role $src */
$src = RoleMapper::getInstance()->findById($clone['src']);

if ($clone['dest'] == 0) {
    // new role
    $dest = new Role();
    $dest->setName($clone['new']);
    $dest->setSequence($src->getSequence());
    $dest->setUpdatedBy($_SESSION['user']->getId());
} else {
    $dest = RoleMapper::getInstance()->findById($clone['dest']);
}

try {
    $kam = new RoleCloner(Database::getInstance());
    $kam->cloneRole($src, $dest);
} catch (Exception $e) {
    error(9, $e->getMessage(), 500);
}

SitePage::localRedir('/adminv2/user');
exit;
