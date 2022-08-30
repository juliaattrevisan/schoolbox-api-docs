<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use PDOStatement;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as PastoralGroupMember;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_report');
        $this->hasModel(Model::class);
        $this->joins('owner_id', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('severity_id', PastoralSeverityMapper::class, 'id', null, null, 'severity', 'severity');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('uses_relative_date', self::TYPE_BOOLEAN);
        $this->uses('relative_date_string', self::TYPE_STRING);
        $this->uses('before_date', self::TYPE_DATETIME);
        $this->uses('after_date', self::TYPE_DATETIME);
        $this->uses('involved', self::TYPE_BOOLEAN);

        // Filter criteria CSV strings

        $this->uses('types', self::TYPE_STRING, null, 'typesCsv');
        $this->uses('sub_types', self::TYPE_STRING, null, 'subTypesCsv');
        $this->uses('authors', self::TYPE_STRING, null, 'authorsCsv');
        $this->uses('students', self::TYPE_STRING, null, 'studentsCsv');
        $this->uses('tags', self::TYPE_STRING, null, 'tagsCsv');
        $this->uses('order', self::TYPE_STRING, null, 'orderCsv');
        $this->uses('years', self::TYPE_STRING, null, 'yearsCsv');
        $this->uses('houses', self::TYPE_STRING, null, 'housesCsv');
        $this->uses('campuses', self::TYPE_STRING, null, 'campusesCsv');
        $this->uses('actions', self::TYPE_STRING, null, 'actionsCsv');

        // Are students with no year or house included?
        $this->uses('include_students_without_year', self::TYPE_BOOLEAN);
        $this->uses('include_students_without_house', self::TYPE_BOOLEAN);

        $this->uses('include_records_without_action', self::TYPE_BOOLEAN);
        $this->uses('include_uncompleted_actions_only', self::TYPE_BOOLEAN);
        $this->uses('notification_enabled', self::TYPE_BOOLEAN);

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(Mapper::CALLBACK_POSTSAVE, [$this, 'saveGroups']);
    }

    /**
     * Update group access to the pastoral report
     *
     * @param Model $model
     */
    public function saveGroups(Model $model)
    {
        $groupIds = [];

        if ($model->getGroups()->count() > 0) {
            foreach ($model->getGroups() as $group) {
                $groupIds[] = $group->getId();
            }

            $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

            // Delete any existing pastoral groups from the report that are no longer to be given access

            $sql = "
                DELETE FROM `pastoral_report_groups`
                WHERE       `report_id` = :reportId AND
                            `group_id` NOT IN ({$bindings['placeholders']})
            ";

            $statement = $this->getDatabase()->prepare($sql);
            $statement->execute(array_merge($bindings['bindings'], [
                'reportId' => $model->getId(),
            ]));
        } else {
            $statement = $this->getDatabase()->prepare("
                DELETE FROM `pastoral_report_groups`
                WHERE       `report_id` = :reportId
            ");

            $statement->execute(['reportId' => $model->getId()]);
        }

        // Insert any new groups

        foreach ($groupIds as $groupId) {
            $sql = "
                INSERT IGNORE INTO `pastoral_report_groups` (
                    report_id,
                    group_id
                )
                VALUES (
                  :reportId,
                  :groupId
                )
            ";

            $statement = $this->getDatabase()->prepare($sql);
            $statement->execute([
                'reportId'      => $model->getId(),
                'groupId'       => $groupId,
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $model = $this->hydrate($row);

            if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
                if (!empty($row['severity_id'])) {
                    /** @var null|PastoralSeverity $severity */
                    try {
                        $severity = $this->getJoinMapper('severity')->findById($row['severity_id']);
                    } catch (MapperException $e) {
                        $severity = null;
                    }
                    $model->setSeverity($severity);
                }
            }

            if (!empty($row['dashboard_id'])) {
                $model->setDashboard(true);
            }

            $model->setInvolved($row['involved'] == 1);

            // Should probably be storing report criteria as a bunch of pivot tables, but this will do for now.

            // This takes the report criteria which are stored in the database as CSV strings and turns them into collections
            // (of hydrated objects where applicable)

            $model->setOrder(new Collection(explode(',', $row['order'])));
            $model->setYears(new Collection(explode(',', $row['years'])));

            $model->setTypes($this->getCollection(PastoralTypeMapper::getInstance(), $row['types']));
            $model->setSubTypes($this->getCollection(PastoralSubTypeMapper::getInstance(), $row['sub_types']));
            $model->setAuthors($this->getCollection(UserMapper::getInstance(), $row['authors']));
            $model->setStudents($this->getCollection(UserMapper::getInstance(), $row['students']));
            $model->setTags($this->getCollection(PastoralTagMapper::getInstance(), $row['tags']));
            $model->setGroups($this->getCollection(PastoralGroupMapper::getInstance(), $row['groups'] ?? null));
            $model->setHouses($this->getCollection(\HouseMapper::getInstance(), $row['houses']));
            $model->setCampuses($this->getCollection(CampusMapper::getInstance(), $row['campuses']));
            if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                $model->setActions($this->getCollection(\Schoolbox\Pastoral\Action\Mapper::getInstance(), $row['actions']));
            }
            $collection->add($model);
        }

        return $collection;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id`
        ";
    }

    /**
     * Retrieve all report definitions the user has access to
     *
     * @param User $user
     *
     * @return Collection|\Schoolbox\GenericCollection
     */
    public function findForUser(User $user)
    {
        $userId = $this->getDatabase()->quote($user->getId());

        $groupsMembership = PastoralGroupMemberMapper::getInstance()->findByColumn('user_id', $user->getId());

        $groupIds = [];

        /** @var PastoralGroupMember $groupMember */
        foreach ($groupsMembership as $groupMember) {
            $groupIds[] = $groupMember->getGroup()->getId();
        }

        $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

        if (!empty($bindings['placeholders'])) {
            $groupSql = "`group_id` IN ({$bindings['placeholders']}) ";
        } else {
            // match no reports by group
            $groupSql = 'TRUE = FALSE';
        }

        $sql = "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`,
                        `pastoral_report_dashboard`.`id` as `dashboard_id`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id`
            LEFT JOIN   `pastoral_report_dashboard` ON
                            `pastoral_report_dashboard`.`report_id` = `pastoral_report`.`id` AND
                            `pastoral_report_dashboard`.`user_id` = {$userId} AND
                            `pastoral_report_dashboard`.`deleted_at` IS NULL
            WHERE       (`pastoral_report`.`owner_id` = :userId OR (
                            `pastoral_report`.`id` IN (
                                SELECT    `report_id`
                                FROM      `pastoral_report_groups`
                                WHERE     {$groupSql}
                            )
                        )) AND
                        `pastoral_report`.`deleted_at` IS NULL
            GROUP BY    `pastoral_report`.`id`
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute(array_merge($bindings['bindings'], [
            'userId' => $user->getId(),
        ]));

        return $this->hydrateSelect($statement);
    }

    /**
     * Retrieve all report definitions that should display on users dashboard
     *
     * @param User $user
     *
     * @return Collection|\Schoolbox\GenericCollection
     */
    public function findForUserDashboard(User $user)
    {
        $userId = $this->getDatabase()->quote($user->getId());

        $groupsMembership = PastoralGroupMemberMapper::getInstance()->findByColumn('user_id', $user->getId());

        $groupIds = [];

        /** @var PastoralGroupMember $groupMember */
        foreach ($groupsMembership as $groupMember) {
            $groupIds[] = $groupMember->getGroup()->getId();
        }

        $bindings = PredicateBuilder::getArrayBindings($groupIds, 'group');

        if (!empty($bindings['placeholders'])) {
            $groupSql = "`group_id` IN ({$bindings['placeholders']}) ";
        } else {
            // match no reports by group
            $groupSql = 'TRUE = FALSE';
        }

        $sql = "

            SELECT      {$this->getColumnString()},
                        GROUP_CONCAT(`pastoral_report_groups`.`group_id`) as `groups`
            FROM        {$this->getTable()}
            LEFT JOIN   `pastoral_report_groups` ON
                            `pastoral_report_groups`.`report_id` = `pastoral_report`.`id`
            JOIN        `pastoral_report_dashboard` ON
                            `pastoral_report_dashboard`.`report_id` = `pastoral_report`.`id` AND
                            `pastoral_report_dashboard`.`user_id` = {$userId} AND
                            `pastoral_report_dashboard`.`deleted_at` IS NULL
            WHERE       `pastoral_report`.`deleted_at` IS NULL AND
            (`pastoral_report`.`owner_id` = :userId OR (
                            `pastoral_report`.`id` IN (
                                SELECT    `report_id`
                                FROM      `pastoral_report_groups`
                                WHERE     {$groupSql}
                            )
                        ))
            GROUP BY    `pastoral_report`.`id`
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute(array_merge($bindings['bindings'], [
            'userId' => $user->getId(),
        ]));

        return $this->hydrateSelect($statement);
    }


    /**
     * @param \Schoolbox\Pastoral\Model $pastoral
     * @param bool                      $notificationEnabledOnly
     * @return \Schoolbox\GenericCollection
     */
    public function findForRecord(\Schoolbox\Pastoral\Model $pastoral, bool $notificationEnabledOnly)
    {
        // For now, this does not check date, when uses_relative_date is true.

        $sql = "
            SELECT      {$this->getColumnString()}
            FROM        {$this->getTable()}
            WHERE
                  (
                      `uses_relative_date` = 1
                      OR
                      (
                          (`after_date` IS NULL OR `after_date` <= :createdAt)
                          AND
                          (`before_date` IS NULL OR :createdAt <= `before_date`)
                      )
                  )
                  AND (`authors` IS NULL OR CONCAT(',', `authors`, ',') LIKE :authorLike)
                  AND (`students` IS NULL OR CONCAT(',', `students`, ',') LIKE :studentLike)
     ";

        $wheres = [];
        $params = [
            'createdAt'   => $pastoral->getCreatedAt()->getDatabase(),
            'authorLike'  => "%,{$pastoral->getOwner()->getId()},%",
            'studentLike' => "%,{$pastoral->getStudent()->getId()},%",
        ];

        // TODO involved

        // Severity
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
            // show all severity levels = severity_id = null
            if ($pastoral->getSeverity()) {
                $wheres[]                   = "(`severity_id` IS NULL OR :severitySequence >= `severity_id`)";
                $params['severitySequence'] = $pastoral->getSeverity()->getSequence();
            } else {
                $wheres[] = "`severity_id` IS NULL";
            }
        }

        // Type
        if ($pastoral->getType()) {
            $wheres[]           = "(`types` IS NULL OR CONCAT(',', `types`, ',') LIKE :typeLike)";
            $params['typeLike'] = "%,{$pastoral->getType()->getId()},%";
        } else {
            // both pastoral record and report should not have this field null, but...
            $wheres[] = "`types` IS NULL";
        }

        // SubType
        // when subtype is empty, do not check report's sub_types field
        if ($pastoral->getSubType()) {
            $wheres[]              = "(`sub_types` IS NULL OR CONCAT(',', `sub_types`, ',') LIKE :subTypeLike)";
            $params['subTypeLike'] = "%,{$pastoral->getSubType()->getId()},%";
        }

        // Tag
        $tagWheres = [];
        foreach ($pastoral->getTags() as $i => $tag) {
            $tagWheres[]         = "CONCAT(',', `tags`, ',') LIKE :tagLike$i";
            $params["tagLike$i"] = "%,{$tag->getId()},%";
        }
        if ($pastoral->getTags()->count() === 0) {
            // this field is set to empty string, for types without subtype
            $wheres[] = "`tags` = ''";
        } else {
            $wheres[] = sprintf("(`tags` = '' OR %s)", implode(' OR ', $tagWheres));
        }

        // Year level
        if ($pastoral->getStudent()->getYear()) {
            $wheres[]           = "CONCAT(',', `years`, ',') LIKE :yearLike";
            $params["yearLike"] = "%,{$pastoral->getStudent()->getYear()->getId()},%";
        } else {
            $wheres[] = "`include_students_without_year` = 1";
        }

        // House
        if ($pastoral->getStudent()->getSchoolHouse()) {
            $wheres[]            = "CONCAT(',', `houses`, ',') LIKE :houseLike";
            $params["houseLike"] = "%,{$pastoral->getStudent()->getSchoolHouse()->getId()},%";
        } else {
            $wheres[] = "`include_students_without_house` = 1";
        }

        // Campus
        $campusWheres = [];
        foreach ($pastoral->getStudent()->getCampusCollection() as $i => $campus) {
            $campusWheres[]         = "CONCAT(',', `campuses`, ',') LIKE :campusLike$i";
            $params["campusLike$i"] = "%,{$campus->getId()},%";
        }
        $wheres[] = sprintf("(%s)", implode(' OR ', $campusWheres));

        // Action
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            $actionWheres = [];
            foreach ($pastoral->getRecordActions() as $i => $action) {
                if ($action->getCompleted()) {
                    $actionWheres[] = "(CONCAT(',', `actions`, ',') LIKE :actionLike$i AND `include_uncompleted_actions_only` = 0)";
                } else {
                    $actionWheres[] = "CONCAT(',', `actions`, ',') LIKE :actionLike$i";
                }
                $params["actionLike$i"] = "%,{$action->getId()},%";
            }
            if ($pastoral->getRecordActions()->count() === 0) {
                $wheres[] = "`actions` = '' OR `actions` IS NULL OR `include_records_without_action` = 1";
            } else {
                $wheres[] = sprintf("(`actions` = '' OR `actions` IS NULL OR %s)", implode(' OR ', $actionWheres));
            }
        }

        // Notification enabled only
        if ($notificationEnabledOnly) {
            $wheres[] = "`notification_enabled ` = 1";
        }

        $sql .= ' AND ' . implode(' AND ', $wheres);

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute($params);

        return $this->hydrateSelect($statement);
    }

    /**
     * Helper function to convert CSV filters to a collection of objects
     *
     * @param BaseMapper $mapper
     * @param string     $ids
     *
     * @return Collection
     */
    private function getCollection($mapper, $ids)
    {
        $collection = $mapper->createCollection();

        $ids = trim($ids);

        if (!empty($ids)) {
            $array = explode(",", $ids);

            foreach ($array as $id) {
                try {
                    if (is_numeric($id)) {
                        $object = $mapper->findById($id);
                    } else {
                        $object = $mapper->findOneByColumn('name', $id);
                    }
                    $collection->add($object);
                } catch (MapperException $e) {
                }
            }
        }

        return $collection;
    }
}
