<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

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
     * @throws Mapper\Exception
     */
    public function cloneRole(Role $source, Role $destination): void
    {
        // copy params (leave name and ID alone)
        $destination->setType($source->getType());
        $destination->setDigestSchedule($source->getDigestSchedule());
        $destination->setCanChangePassword($source->getCanChangePassword());
        $destination->setViewStudentProfile($source->getViewStudentProfile());
        $destination->setViewStudentGrades($source->getViewStudentGrades());
        $destination->setViewStudentCalendar($source->getViewStudentCalendar());
        $destination->setViewParentProfile($source->getViewParentProfile());
        $destination->setAccessEportfolio($source->getAccessEportfolio());
        $destination->setAccessPastoral($source->getAccessPastoral());
        $destination->setCreateBadges($source->getCreateBadges());
        $destination->setAccessEvidence($source->getAccessEvidence());
        $destination->setViewParentDirectory($source->getViewParentDirectory());
        $destination->setViewStaffDirectory($source->getViewStaffDirectory());

        // persist that
        RoleMapper::getInstance()->save($destination);

        // and now onto copying the relevant data
        $this->cloneSideNav($source, $destination);
        $this->cloneTopNav($source, $destination);
        $this->cloneDashboard($source, $destination);
        $this->cloneCategoryPermissions($source, $destination);
        $this->cloneCommsArticleAudiences($source, $destination);
        $this->cloneSchoolCalendarPermissions($source, $destination);
        $this->cloneTours($source, $destination);

        //notification transfer...
        $this->cloneNotifs($source, $destination);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneSideNav(Role $source, Role $destination): void
    {
        $this->clonePerms("
            DELETE FROM `menu_side_role` WHERE `role_id` = ?
        ", "
            INSERT INTO `menu_side_role` (menu_side_id, role_id)
            SELECT menu_side_id, :dest
            FROM `menu_side_role`
            WHERE `role_id` = :src
        ", [
            'src'  => $source->getId(),
            'dest' => $destination->getId(),
        ]);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneTopNav(Role $source, Role $destination): void
    {
        $this->clonePerms("
            DELETE FROM `menu_top_role` WHERE `role_id` = ?
        ", "
            INSERT INTO `menu_top_role` (menu_top_id, role_id)
            SELECT menu_top_id, :dest
            FROM `menu_top_role`
            WHERE `role_id` = :src
        ", [
            'src'  => $source->getId(),
            'dest' => $destination->getId(),
        ]);
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneDashboard(Role $source, Role $destination): void
    {
        // most of the select query stays the same
        $queryString = "
        FROM component_instance ci
        LEFT JOIN component_config_instance cci ON cci.component_instance_id = ci.id
        WHERE module = 'dashboard'
        AND identifier = ?
    ";

        // delete old dashboard
        $this->db->query("DELETE ci, cci $queryString", [$destination->getId()]);

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
    ", [$source->getId()]);

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
                'inst_id' => $instId,
            ]);

            // retrieve the new instanceId we just created
            $newInstId = $this->db->lastInsertId();

            if (!empty($inst['conf'])) {
                foreach ($inst['conf'] as $defnId => $value) {
                    // if there was a config for the instance, copy that across
                    $insertConfStmt->execute([
                        'ccd' => $defnId,
                        'ci'  => $newInstId,
                        'val' => $value,
                    ]);
                }
            }
        }
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneCategoryPermissions(Role $source, Role $destination): void
    {
        $this->permClone($source, $destination, 'perms', ['cid', ':dest', '`type`']);
    }

    /**
     * Clone role audiences for comms articles.
     *
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneCommsArticleAudiences(Role $source, Role $destination): void
    {
        // Non-composite permissions: fairly simple, just clone the audience
        $this->clonePerms(
            "
                DELETE FROM comms_news_audience
                WHERE entity_type  = 'role'
                AND   entity_value = ?
            ",
            "
                INSERT INTO comms_news_audience
                    (comms_news_article_id, entity_type, entity_value, relationship)
                SELECT comms_news_article_id, entity_type, :dest, relationship
                FROM   comms_news_audience
                WHERE  entity_type  = 'role'
                AND    entity_value = :src
            ",
            ['src'  => $source->getId(), 'dest' => $destination->getId(),]
        );

        // Composite permissions cleanup
        $this->db->query(
            "
                DELETE FROM comms_news_audience_complex
                WHERE
                ( entity_type_1  = 'role' AND entity_value_1 = ? )
                OR
                (entity_type_2 = 'role' AND entity_value_2 = ? )",
            [$destination->getId(), $destination->getId()]
        );

        // Copy first set of complex audiences
        $this->db->query(
            "
                INSERT INTO comms_news_audience_complex
                    (comms_news_article_id, entity_type_1, entity_value_1, entity_type_2, entity_value_2)
                SELECT comms_news_article_id, entity_type_1, :dest, entity_type_2, entity_value_2
                FROM   comms_news_audience_complex
                WHERE  entity_type_1  = 'role'
                AND    entity_value_1 = :src
            ",
            ['src'  => $source->getId(), 'dest' => $destination->getId(),]
        );

        //And second set of composite permissions
        $this->db->query(
            "INSERT INTO comms_news_audience_complex
            (comms_news_article_id, entity_type_1, entity_value_1, entity_type_2, entity_value_2)
            SELECT comms_news_article_id, entity_type_1, entity_value_1, entity_type_2, :dest
            FROM   comms_news_audience_complex
            WHERE  entity_type_2  = 'role'
            AND    entity_value_2 = :src",
            ['src'  => $source->getId(), 'dest' => $destination->getId(),]
        );
    }

    /**
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneSchoolCalendarPermissions(Role $source, Role $destination)
    {
        $this->permClone($source, $destination, 'event_role', ['event_id', ':dest'], 'role_id');
    }

    /**
     * @param Role   $source
     * @param Role   $destination
     * @param string $table
     * @param array  $select
     * @param string $field
     */
    protected function permClone(Role $source, Role $destination, $table, array $select, $field = 'gid'): void
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
            'dest' => $destination->getId(),
        ]);
    }

    /**
     * Clones tours.
     * @param Role $source
     * @param Role $destination
     */
    protected function cloneTours(Role $source, Role $destination): void
    {
        $this->clonePerms("
            DELETE FROM `tour_audience_role` WHERE `role_id` = ?
        ", "
            INSERT INTO `tour_audience_role` (moderator_user_id, tour_uuid, role_id)
            SELECT moderator_user_id, tour_uuid, :dest
            FROM `tour_audience_role`
            WHERE `role_id` = :src
        ", [
            'src'  => $source->getId(),
            'dest' => $destination->getId(),
        ]);
    }

    /**
     * @param string $deleteQuery
     * @param string $insertQuery
     * @param array  $args
     */
    protected function clonePerms($deleteQuery, $insertQuery, $args): void
    {
        // clear old perms
        $this->db->query($deleteQuery, [$args['dest']]);

        // insert new perms
        $this->db->query($insertQuery, $args);
    }

    /**
     * migrate all notification settings to $destination, and ignore what is already there.
     * @param Role $source
     * @param Role $destination
     * @return void
     */
    protected function cloneNotifs(Role $source, Role $destination)
    {
        /**
         * purge /  burn all notification settings from $destination
         * with those from $source
         */
        $this->db->query("
        REPLACE INTO msg_route_perm ( SELECT rp.msg_source_id as msg_source_id,
                :newRole as role_id,
                rp.mask as mask,
                rp.role_default as role_default
            FROM msg_route_perm as rp
            WHERE rp.role_id = :oldRole
        )", ['newRole' => $destination->getId(), 'oldRole' => $source->getId()]);
    }
}
