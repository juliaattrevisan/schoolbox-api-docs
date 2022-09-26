<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use PDOStatement;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Report\Access\Mapper as ReportAccessMapper;
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

        $this->hasJoin(
            'access',
            [
                'multiple'  => true,
                'mapper'    => ReportAccessMapper::class,
                'this_key'  => 'id',
                'other_key' => 'pastoral_report_id',
            ]
        );

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveAccess']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteAccess']);
    }

    /**
     * Update access to the pastoral report
     *
     * @param Model $model
     * @return void
     * @throws MapperException
     */
    public function saveAccess(Model $model)
    {
        $reportAccessMapper = $this->getJoinMapper('access');
        $reportAccessMapper->deleteByColumn('pastoral_report_id', $model->getId());
        $reportAccessMapper->save($model->getAccess());
    }

    /**
     * Delete access to the pastoral report
     *
     * @param Model $model
     * @return void
     */
    public function deleteAccess(Model $model)
    {
        $reportAccessMapper = $this->getJoinMapper('access');
        $reportAccessMapper->deleteByColumn('pastoral_report_id', $model->getId());
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
            $model->setYears($this->getCollection(\Schoolbox\YearLevel\Mapper::getInstance(), $row['years']));
            $model->setTypes($this->getCollection(PastoralTypeMapper::getInstance(), $row['types']));
            $model->setSubTypes($this->getCollection(PastoralSubTypeMapper::getInstance(), $row['sub_types']));
            $model->setAuthors($this->getCollection(UserMapper::getInstance(), $row['authors']));
            $model->setStudents($this->getCollection(UserMapper::getInstance(), $row['students']));
            $model->setTags($this->getCollection(PastoralTagMapper::getInstance(), $row['tags']));
            $model->setAccess($this->getJoinMapper('access')->findByColumn('pastoral_report_id', $row['id']));
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
     * Retrieve all report definitions the user has access to
     *
     * @param User $user
     * @param bool $dashboardOnly
     * @return GenericCollection
     */
    public function findForUser(User $user, bool $dashboardOnly): GenericCollection
    {
        $dashboardJoin = $dashboardOnly ? 'JOIN' : 'LEFT JOIN';

        $sql = "
            SELECT {$this->getColumnString('report')},
                   `dashboard`.`id` as dashboard_id,
                   {$this->getJoinColumnString('access', 'access')}
            FROM `{$this->getTable()}` `report`
                LEFT JOIN `pastoral_report_access` `access` ON `access`.`pastoral_report_id` = `report`.`id`
                {$dashboardJoin} `pastoral_report_dashboard` `dashboard` ON `dashboard`.`report_id` = `report`.`id`
                                                                 AND `dashboard`.`user_id` = :userId
                                                                 AND `dashboard`.`deleted_at` IS NULL
            WHERE `report`.`deleted_at` IS NULL
              AND (
                    `report`.`owner_id` = :userId
                    OR
                    (`access`.`user_id` = :userId)
                    OR
                    (`access`.`group_id` IN
                      (SELECT id FROM `pastoral_group` WHERE `user_id` = :userId AND `deleted_at` IS NULL)
                    )
                  )
        ";

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute(['userId' => $user->getId()]);

        return $this->getHydratedResult($statement);
    }

    /**
     * Find reports which criteria match the pastoral record provided
     * Please note:
     * * This does not check the current user's accessibility to the record
     * * This does not check involved report flag, as it's expensive
     *
     * @param \Schoolbox\Pastoral\Model $pastoral
     * @param bool                      $notificationEnabledOnly
     * @return \Schoolbox\GenericCollection
     */
    public function findForRecord(\Schoolbox\Pastoral\Model $pastoral, bool $notificationEnabledOnly): GenericCollection
    {
        // For now, this does not check date, when uses_relative_date is true.

        $sql = "
            SELECT {$this->getColumnString('report')},
                   {$this->getJoinColumnString('access', 'access')}
            FROM `{$this->getTable()}` `report`
            LEFT JOIN `pastoral_report_access` `access` ON `access`.`pastoral_report_id` = `report`.`id`
            WHERE `report`.`deleted_at` IS NULL
                  AND (
                      `report`.`uses_relative_date` = 1
                      OR
                      (
                          (`report`.`after_date` IS NULL OR `report`.`after_date` <= :createdAt)
                          AND
                          (`report`.`before_date` IS NULL OR :createdAt <= `report`.`before_date`)
                      )
                  )
                  AND (`report`.`authors` IS NULL OR CONCAT(',', `report`.`authors`, ',') LIKE :authorLike)
                  AND (`report`.`students` IS NULL OR CONCAT(',', `report`.`students`, ',') LIKE :studentLike)
     ";

        $wheres = [];
        $params = [
            'createdAt'   => $pastoral->getCreatedAt()->getDatabase(),
            'authorLike'  => "%,{$pastoral->getOwner()->getId()},%",
            'studentLike' => "%,{$pastoral->getStudent()->getId()},%",
        ];

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
            $wheres[] = "`tags` IS NULL";
        } else {
            $wheres[] = sprintf("(`tags` IS NULL OR %s)", implode(' OR ', $tagWheres));
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
                $wheres[] = "`actions` IS NULL OR `include_records_without_action` = 1";
            } else {
                $wheres[] = sprintf("(`actions` IS NULL OR %s)", implode(' OR ', $actionWheres));
            }
        }

        // Notification enabled only
        if ($notificationEnabledOnly) {
            $wheres[] = "`notification_enabled` = 1";
        }

        $sql .= ' AND ' . implode(' AND ', $wheres) . ' GROUP BY `report`.`id`';

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute($params);
        return $this->getHydratedResult($statement);
    }

    /**
     * Helper function to convert CSV filters to a collection of objects
     *
     * @param BaseMapper $mapper
     * @param string     $ids
     *
     * @return GenericCollection
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

    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    private function getHydratedResult(PDOStatement $statement): GenericCollection
    {
        $reportAccessMapper = $this->getJoinMapper('access');

        $collection = $this->createCollection();
        foreach ($statement as $row) {
            if ($collection->offsetExists($row['report.id'])) {
                $model = $collection->offsetGet($row['report.id']);
            } else {
                try {
                    $model = $this->hydrate($row, 'report');
                    if ($row['dashboard_id'] !== null) {
                        $model->setDashboard(true);
                    }
                } catch (MapperException $e) {
                    continue;
                }
            }
            try {
                $accessList = $reportAccessMapper
                    ->findByColumn('pastoral_report_id', $model->getId())
                    ->sortByCallback(static function ($a, $b) {
                        /** @var \Schoolbox\Pastoral\Report\Access\Model $a */
                        /** @var \Schoolbox\Pastoral\Report\Access\Model $b */
                        if ($a->getUser() && $b->getUser()) {
                            if ($a->getUser()->getLastname() === $b->getUser()->getLastname()) {
                                return strnatcasecmp($a->getUser()->getFirstname(), $b->getUser()->getFirstname());
                            }
                            return strnatcasecmp($a->getUser()->getLastname(), $b->getUser()->getLastName());
                        }
                        if ($a->getGroup() && $b->getGroup()) {
                            return strnatcasecmp($a->getGroup()->getName(), $b->getGroup()->getName());
                        }
                        if ($a->getGroup() === null) {
                            return -1;
                        }
                        return 1;
                    });
                $model->setAccess($accessList);
            } catch (MapperException $e) {
            }
            $collection->offsetSet($row['report.id'], $model);
        }
        return $collection;
    }
}
