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
    permClone($src, $dest, 'sideMenuGroup', [ 'id', ':dest' ]);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneDashboard(Role $src, Role $dest)
{
    // most of the select query stays the same
    $queryString = "
        FROM component_instance ci
        LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
        WHERE module = 'dashboard'
        AND identifier = ?
    ";

    // delete old dashboard
    Database::getInstance()->query("DELETE ci, cci $queryString", [ $dest->getId() ]);

    // replicate target dashboard

    // statement for duplicating a given instance
    $insertInstStmt = Database::getInstance()->prepare("
        INSERT INTO component_instance
        SELECT null, component_definition_id, module, :dest, position, sequence
        FROM component_instance
        WHERE id = :inst_id
    ");

    // statement for inserting new config for the given instance
    $insertConfStmt = Database::getInstance()->prepare("
        INSERT INTO component_config_instance
          (component_config_definition_id, component_instance_id, value)
        VALUES
          (:ccd, :ci, :val)
    ");

    // collect all the instances we're going to copy
    $stmt = Database::getInstance()->query("
        SELECT ci.id inst_id, cci.`component_config_definition_id` defn_id, `value`
        $queryString
        ORDER BY inst_id ASC
    ", [ $src->getId() ]);

    // index by inst_id
    $instances = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $instances[$r['inst_id']]['id'] = $r['inst_id'];
        if ($r['value']) {
            $instances[$r['inst_id']]['conf'][$r['defn_id']] = $r['value'];
        }
    }

    foreach ($instances as $inst) {
        $instId = $inst['id'];

        // insert the new instance
        $insertInstStmt->execute([
            'dest'    => $dest->getId(),
            'inst_id' => $instId
        ]);

        // retrieve the new instanceId we just created
        $newInstId = Database::getInstance()->lastInsertId();

        foreach ($inst['conf'] as $defnId => $value) {
            // if there was a config for the instance, copy that across
            $insertConfStmt->execute([
                'ccd' => $defnId,
                'ci'  => $newInstId,
                'val' => $value
            ]);
        }
    }

}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneCategoryPermissions(Role $src, Role $dest)
{
    permClone($src, $dest, 'perms', [ 'cid', ':dest', '`type`' ]);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolNewsPermissions(Role $src, Role $dest)
{
    permClone($src, $dest, 'newsGroup', [ 'id', ':dest' ]);
}

/**
 * @param Role $src
 * @param Role $dest
 */
function cloneSchoolCalendarPermissions(Role $src, Role $dest)
{
    permClone($src, $dest, 'event_group', [ 'event_id', ':dest' ], 'group_id');
}

/**
 * @param Role   $src
 * @param Role   $dest
 * @param string $table
 * @param array  $select
 * @param string $field
 */
function permClone(Role $src, Role $dest, $table, array $select, $field = 'gid')
{
    $selects = implode(', ', $select);

    clonePerms("
        DELETE FROM `$table` WHERE `$field` = ?
    ", "
        INSERT INTO `$table`
        SELECT $selects
        FROM `$table`
        WHERE `$field` = :src
    ", [
        'src'  => $src->getId(),
        'dest' => $dest->getId()
    ]);
}

/**
 * @param string $deleteQuery
 * @param string $insertQuery
 * @param array $args
 */
function clonePerms($deleteQuery, $insertQuery, $args)
{
    // clear old perms
    Database::getInstance()->query($deleteQuery, [ $args['dest'] ]);

    // insert new perms
    Database::getInstance()->query($insertQuery, $args);
}