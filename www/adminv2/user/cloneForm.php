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

    // persist that
    RoleMapper::getInstance()->save($dest);

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
    clonePerms("
        DELETE FROM sideMenuGroup WHERE gid = ?
    ", "
        INSERT INTO sideMenuGroup
        SELECT id, :dest
        FROM sideMenuGroup
        WHERE gid = :src
    ", [
        'src' => $src->getId(),
        'dest' => $dest->getId()
    ]);
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
    clonePerms("
        DELETE FROM perms WHERE gid = ?
    ", "
        INSERT INTO perms
        SELECT cid, :dest, `type`
        FROM perms
        WHERE gid = :src
    ", [
        'src' => $src->getId(),
        'dest' => $dest->getId()
    ]);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolNewsPermissions(Role $src, Role $dest)
{
    clonePerms("
        DELETE FROM newsGroup WHERE gid = ?
    ", "
        INSERT INTO newsGroup
        SELECT id, :dest
        FROM newsGroup
        WHERE gid = :src
    ", [
        'src' => $src->getId(),
        'dest' => $dest->getId()
    ]);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolCalendarPermissions(Role $src, Role $dest)
{
    clonePerms("
        DELETE FROM event_group WHERE group_id = ?
    ", "
        INSERT INTO event_group
        SELECT event_id, :dest
        FROM event_group
        WHERE group_id = :src
    ", [
        'src' => $src->getId(),
        'dest' => $dest->getId()
    ]);
}


function clonePerms($deleteQuery, $insertQuery, $args)
{
    // clear old perms
    Database::getInstance()->query($deleteQuery, [ $args['dest'] ]);

    // insert new perms
    Database::getInstance()->query($insertQuery, $args);
}