<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Report;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Report\Access\Mapper as ReportAccessMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
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
        $this->uses('include_archived_records', self::TYPE_BOOLEAN);
        $this->uses('involved', self::TYPE_BOOLEAN);

        // Summary table options
        $this->uses('summary_tables', self::TYPE_STRING);

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
        $this->uses('folders', self::TYPE_STRING, null, 'foldersCsv');
        $this->uses('actions', self::TYPE_STRING, null, 'actionsCsv');

        // Are students with no year or house included?
        $this->uses('include_students_without_year', self::TYPE_BOOLEAN);
        $this->uses('include_students_without_house', self::TYPE_BOOLEAN);

        $this->uses('include_records_without_action', self::TYPE_BOOLEAN);
        $this->uses('action_status', self::TYPE_STRING, self::COLUMN_ENUM, null, [
            Model::ACTION_STATUS_PENDING,
            Model::ACTION_STATUS_COMPLETED,
            null,
        ]);
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
            $model->setFolders($this->getCollection(\Schoolbox\Resource\Folder\Mapper::getInstance(), $row['folders']));
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

        // if current user is moderator, get all shared reports
        // regardless of whether the pastoral group is deleted or pastoral group member doesn't exist
        if ($user->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL) !== null) {
            $userQuery = "
                    `access`.`user_id` IS NOT NULL
                    OR
                    `access`.`group_id` IS NOT NULL";
        } else {
            $userQuery = "
                    (`access`.`user_id` = :userId)
                    OR
                    (`access`.`group_id` IN
                      (SELECT `group_id` FROM `pastoral_group` JOIN `pastoral_group_member` ON `pastoral_group`.`id` = `pastoral_group_member`.`group_id`
                        AND `pastoral_group_member`.`user_id` = :userId
                        AND `pastoral_group_member`.`deleted_at` IS NULL
                        AND `pastoral_group`.`deleted_at` IS NULL)
                    ) ";
        }

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
                    {$userQuery}
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
        $sql = "
            SELECT {$this->getColumnString('report')},
                   {$this->getJoinColumnString('access', 'access')},
                   {$this->getJoinColumnString('severity', 'severity')}
            FROM `{$this->getTable()}` `report`
            LEFT JOIN `pastoral_report_access` `access` ON `access`.`pastoral_report_id` = `report`.`id`
            LEFT JOIN `lu_pastoral_severity` `severity` ON `severity`.`id` = `report`.`severity_id`
            WHERE `report`.`deleted_at` IS NULL
                  AND (`report`.`authors` IS NULL OR CONCAT(',', `report`.`authors`, ',') LIKE :authorLike)
                  AND (`report`.`students` IS NULL OR CONCAT(',', `report`.`students`, ',') LIKE :studentLike)
     ";

        $wheres = [];
        $params = [
            'authorLike'  => "%,{$pastoral->getOwner()->getId()},%",
            'studentLike' => "%,{$pastoral->getStudent()->getId()},%",
        ];

        // Date
        $dateWheres           = [
            ' (`report`.`uses_relative_date` = 0
              AND (`report`.`after_date` IS NULL OR `report`.`after_date` <= :createdAt1 )
              AND (`report`.`before_date` IS NULL OR :createdAt2 <= `report`.`before_date`))',
        ];
        $params['createdAt1'] = $pastoral->getRecordDate()->getDatabase();
        $params['createdAt2'] = $pastoral->getRecordDate()->getDatabase();
        $relativeDates        = $this->getMatchedRelativeDates($pastoral);
        if (!empty($relativeDates)) {
            $bindings     = Builder::getBindings($relativeDates, 'rds');
            $dateWheres[] = " (`report`.`uses_relative_date` = 1
                               AND `report`.`relative_date_string` IN ({$bindings->getPlaceholder()}))";
            $params       = array_merge($params, $bindings->getParams());
        }
        $wheres[] = sprintf("(%s)", implode(' OR ', $dateWheres));

        // Severity
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
            // show all severity levels = severity_id = null
            if ($pastoral->getSeverity()) {
                // higher the sequence, higher the severity
                // find report's record's severity's sequence
                $wheres[]                   = "(`severity_id` IS NULL OR :severitySequence >= `severity`.`sequence`)";
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
            $wheres[]           = "(`years` IS NULL OR CONCAT(',', `years`, ',') LIKE :yearLike)";
            $params["yearLike"] = "%,{$pastoral->getStudent()->getYear()->getId()},%";
        } else {
            $wheres[] = "(`years` IS NULL OR `include_students_without_year` = 1)";
        }

        // House
        if ($pastoral->getStudent()->getSchoolHouse()) {
            $wheres[]            = "(`houses` IS NULL OR CONCAT(',', `houses`, ',') LIKE :houseLike)";
            $params["houseLike"] = "%,{$pastoral->getStudent()->getSchoolHouse()->getId()},%";
        } else {
            $wheres[] = "(`houses` IS NULL OR `include_students_without_house` = 1)";
        }

        // Campus
        $campusWheres = [];
        foreach ($pastoral->getStudent()->getCampusCollection() as $i => $campus) {
            $campusWheres[]         = "CONCAT(',', `campuses`, ',') LIKE :campusLike$i";
            $params["campusLike$i"] = "%,{$campus->getId()},%";
        }
        $campusWheres[] = "`campuses` IS NULL ";
        $wheres[]       = sprintf("(%s)", implode(' OR ', $campusWheres));

        // Folder
        $folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $folderIds    = $folderMapper->findFolderIdsForUser(
            $pastoral->getStudent(),
            true,
            UserAccess::ACCESS_READ,
            false,
            false
        );

        $folderWheres = ["`folders` IS NULL"];
        foreach ($folderIds as $i => $folderId) {
            $folderWheres[]         = "CONCAT(',', `folders`, ',') LIKE :folderLike$i";
            $params["folderLike$i"] = "%,{$folderId},%";
        }
        $wheres[] = sprintf("(%s)", implode(' OR ', $folderWheres));

        // Action
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            if ($pastoral->getRecordActions()->count() === 0) {
                // If this record does not have any action, do not return report which action status is not any
                $wheres[] = "((`actions` IS NULL AND `action_status` IS NULL) OR `include_records_without_action` = 1)";
            } else {
                $actionWheres       = [];
                $hasCompletedAction = false;
                $hasPendingAction   = false;
                /** @var \Schoolbox\Pastoral\RecordAction\Model $action */
                foreach ($pastoral->getRecordActions() as $i => $action) {
                    if ($action->getCompleted()) {
                        $hasCompletedAction = true;
                    } else {
                        $hasPendingAction = true;
                    }
                    $actionWheres[]         = "CONCAT(',', `actions`, ',') LIKE :actionLike$i";
                    $params["actionLike$i"] = "%,{$action->getAction()->getId()},%";
                }
                // If this record has actions, do not return reports with only No Action is selected
                $wheres[] = sprintf("((`actions` IS NULL AND `include_records_without_action` = 0) OR %s)", implode(' OR ', $actionWheres));
                // if this record has both completed and pending, this should appear regardless of report's action status setting
                if ($hasPendingAction && !$hasCompletedAction) {
                    // must not appear on completed only report
                    $wheres[] = sprintf("(`action_status` IS NULL OR `action_status` != '%s')", Model::ACTION_STATUS_COMPLETED);
                } elseif (!$hasPendingAction && $hasCompletedAction) {
                    // must not appear on pending only report
                    $wheres[] = sprintf("(`action_status` IS NULL OR `action_status` != '%s')", Model::ACTION_STATUS_PENDING);
                }
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

        $ids = trim((string)$ids);

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
                    if (isset($row['dashboard_id'])) {
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
                            return strnatcasecmp($a->getUser()->getLastname() ?? '', $b->getUser()->getLastName() ?? '');
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
        return $collection->sortNaturally('name');
    }

    /**
     * Finds relative date strings that matches given pastoral record's occurred date/created date
     * @param \Schoolbox\Pastoral\Model $pastoral
     * @return array
     */
    private function getMatchedRelativeDates(\Schoolbox\Pastoral\Model $pastoral): array
    {
        $now        = \Alaress_Date_Format::now();
        $thisTerm   = \Schoolbox\Assessment\Term\Mapper::getInstance()->findForToday();
        $dateParams = [];

        $dates = [
            PastoralReport::THIS_YEAR        =>
                [
                    'start' => (clone $now)->startOfYear(),
                    'end'   => (clone $now)->endOfYear(),
                ],
            PastoralReport::THIS_SCHOOL_YEAR =>
                [
                    'start' => (clone $now)->getStartOfSchoolYear(),
                    'end'   => (clone $now)->endOfSchoolYear(),
                ],
            PastoralReport::THIS_TERM        =>
                [
                    'start' => $thisTerm->getStart(),
                    'end'   => $thisTerm->getActualFinish(),
                ],
            PastoralReport::PAST_6_MONTHS    =>
                [
                    'start' => \Alaress_Date_Format::newFromTimestamp(strtotime(PastoralReport::PAST_6_MONTHS)),
                    'end'   => $now,
                ],
            PastoralReport::PAST_3_MONTHS    =>
                [
                    'start' => \Alaress_Date_Format::newFromTimestamp(strtotime(PastoralReport::PAST_3_MONTHS)),
                    'end'   => $now,
                ],
            PastoralReport::PAST_MONTH       =>
                [
                    'start' => \Alaress_Date_Format::newFromTimestamp(strtotime(PastoralReport::PAST_MONTH)),
                    'end'   => $now,
                ],
            PastoralReport::PAST_FORTNIGHT   =>
                [
                    'start' => \Alaress_Date_Format::newFromTimestamp(strtotime(PastoralReport::PAST_FORTNIGHT)),
                    'end'   => $now,
                ],
            PastoralReport::PAST_WEEK        =>
                [
                    'start' => \Alaress_Date_Format::newFromTimestamp(strtotime(PastoralReport::PAST_WEEK)),
                    'end'   => $now,
                ],
            PastoralReport::TODAY            =>
                [
                    'start' => (clone $now)->startOfDay(),
                    'end'   => (clone $now)->endOfDay(),
                ],
        ];
        foreach ($dates as $name => $date) {
            if (
                ($date['end'] && $pastoral->getRecordDate()->between($date['start'], $date['end']))
                || $pastoral->getRecordDate()->gte($date['start'])
            ) {
                $dateParams[] = $name;
            }
        }
        return $dateParams;
    }
}
