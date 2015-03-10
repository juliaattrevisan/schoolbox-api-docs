<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Role;
use Schoolbox\RoleMapper;


if (isset($this)) {
    error(2);
}

require_once __DIR__ . '/../../../include/Bootstrap.php';

new SitePage();

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
} else {
    $dest = RoleMapper::getInstance()->findById($clone['dest']);
}

try {
    cloneRole($src, $dest);
} catch (Exception $e) {
    error(9, $e->getMessage(), 500);
}

SitePage::localRedir('/adminv2/user');
exit;


/**
 * @param Role $src
 * @param Role $dest
 */
function cloneRole(Role $src, Role $dest)
{
    // copy params (leave name and ID alone)
    $dest->setType($src->getType());
    $dest->setDigestSchedule($src->getDigestSchedule());
    $dest->setCanChangePassword($src->getCanChangePassword());

    // and now onto copying the relevant data
    cloneSideNav($src, $dest);
    cloneDashboard($src, $dest);
    cloneCategoryPermissions($src, $dest);
    cloneSchoolNewsPermissions($src, $dest);
    cloneSchoolCalendarPermissions($src, $dest);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSideNav(Role $src, Role $dest)
{

}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneDashboard(Role $src, Role $dest)
{

}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneCategoryPermissions(Role $src, Role $dest)
{

}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolNewsPermissions(Role $src, Role $dest)
{

}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolCalendarPermissions(Role $src, Role $dest)
{

}