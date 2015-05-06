<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


use Database;
use PDO;

class RoleCloner
{

    /** @var Database */
    protected $db;

    /**
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }


    /**
     * @param Role $source
     * @param Role $destination
     */
    public function cloneRole(Role $source, Role $destination)
    {
        // copy params (leave name and ID alone)
        $destination->setType($source->getType());
        $destination->setDigestSchedule($source->getDigestSchedule());
        $destination->setCanChangePassword($source->getCanChangePassword());

        // persist that
        RoleMapper::getInstance()->save($destination);

        // and now onto copying the relevant data
        $this->cloneSideNav($source, $destination);
        $this->cloneDashboard($source, $destination);
        $this->cloneCategoryPermissions($source, $destination);
        $this->cloneSchoolNewsPermissions($source, $destination);
        $this->cloneSchoolCalendarPermissions($source, $destination);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneSideNav(Role $source, Role $destination)
    {
        $this->permClone($source, $destination, 'sideMenuGroup', [ 'id', ':dest' ]);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneDashboard(Role $source, Role $destination)
    {
        // most of the select query stays the same
        $queryString = "
        FROM component_instance ci
        LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
        WHERE module = 'dashboard'
        AND identifier = ?
    ";

        // delete old dashboard
        $this->db->query("DELETE ci, cci $queryString", [ $destination->getId() ]);

        // replicate target dashboard

        // statement for duplicating a given instance
        $insertInstStmt = $this->db->prepare("
        INSERT INTO component_instance
        SELECT null, component_definition_id, module, :dest, position, sequence
        FROM component_instance
        WHERE id = :inst_id
    ");

        // statement for inserting new config for the given instance
        $insertConfStmt = $this->db->prepare("
        INSERT INTO component_config_instance
          (component_config_definition_id, component_instance_id, value)
        VALUES
          (:ccd, :ci, :val)
    ");

        // collect all the instances we're going to copy
        $stmt = $this->db->query("
        SELECT ci.id inst_id, cci.`component_config_definition_id` defn_id, `value`
        $queryString
        ORDER BY inst_id ASC
    ", [ $source->getId() ]);

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
                'dest'    => $destination->getId(),
                'inst_id' => $instId
            ]);

            // retrieve the new instanceId we just created
            $newInstId = $this->db->lastInsertId();

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
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneCategoryPermissions(Role $source, Role $destination)
    {
        $this->permClone($source, $destination, 'perms', [ 'cid', ':dest', '`type`' ]);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneSchoolNewsPermissions(Role $source, Role $destination)
    {
        $this->permClone($source, $destination, 'newsGroup', [ 'id', ':dest' ]);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneSchoolCalendarPermissions(Role $source, Role $destination)
    {
        $this->permClone($source, $destination, 'event_group', [ 'event_id', ':dest' ], 'group_id');
    }

    /**
     * @param Role   $source
     * @param Role   $destination
     * @param string $table
     * @param array  $select
     * @param string $field
     */
    protected function permClone(Role $source, Role $destination, $table, array $select, $field = 'gid')
    {
        $selects = implode(', ', $select);

        $this->clonePerms("
        DELETE FROM `$table` WHERE `$field` = ?
    ", "
        INSERT INTO `$table`
        SELECT $selects
        FROM `$table`
        WHERE `$field` = :src
    ", [
            'src'  => $source->getId(),
            'dest' => $destination->getId()
        ]);
    }

    /**
     * @param string $deleteQuery
     * @param string $insertQuery
     * @param array $args
     */
    protected function clonePerms($deleteQuery, $insertQuery, $args)
    {
        // clear old perms
        $this->db->query($deleteQuery, [ $args['dest'] ]);

        // insert new perms
        $this->db->query($insertQuery, $args);
    }
} 