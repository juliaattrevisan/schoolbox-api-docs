<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\DifferentiationProfile;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\Filter\Filters\DifferentiationProfile;
use Schoolbox\Filter\Filters\DifferentiationProfile\Name\Definition;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as PastoralModel;

/**
 * Class Mapper
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 * @method Model findOneByColumn($columnName, $columnValue, array $options = array())
 *
 * @package Schoolbox\DifferentiationProfile
 */
class Mapper extends BaseMapper
{
    /**
     * Configuration mapping between config keys and corresponding model getter methods
     */
    public const array CONFIG_METHOD_MAPPING = [
        'adjustment_category' => 'getAdjustmentCategory',
        'adjustment_level' => 'getAdjustmentLevel',
        'overview' => 'getOverview',
        'interests_strengths' => 'getInterestsStrengths',
        'needs_challenges' => 'getNeedsChallenges',
        'strategies_adjustments' => 'getStrategiesAdjustments',
        'goals' => 'getGoals',
    ];

    public const int DEFAULT_LIMIT = 20;
    public const string FAR_FAR_AWAY_FULL_DATE = '9999-12-31';
    public const string FAR_FAR_AWAY_PAST = '1970-01-01 00:00:00';
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('differentiation_profiles');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('overview', self::TYPE_STRING);
        $this->uses('interests_strengths', self::TYPE_STRING);
        $this->uses('needs_challenges', self::TYPE_STRING);
        $this->uses('strategies_adjustments', self::TYPE_STRING);
        $this->uses('goals', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        /** adjustments */
        $this->uses('adjustment_category', self::TYPE_STRING, null, 'adjustmentCategoryIds');
        $this->uses('adjustment_level', self::TYPE_STRING, null, 'adjustmentLevelIds');
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Model $model */
            $model = $this->hydrate($row);
            $model->setStudent($this->getJoinMapper('student')->hydrate($row, 's'));
            $model->setCreatedBy($this->getJoinMapper('createdBy')->hydrate($row, 'createdBy'));
            $model->setUpdatedBy($this->getJoinMapper('updatedBy')->hydrate($row, 'updatedBy'));
            $model->setAdjustmentCategory($this->getCollection(AdjustmentCategories\Mapper::getInstance(), $row['adjustment_category']));
            $model->setAdjustmentLevel($this->getCollection(AdjustmentLevels\Mapper::getInstance(), $row['adjustment_level']));
            $collection->add($model);
        }

        $collection = $this->loadPastoralRecordsForCollection($collection);

        return $collection;
    }

    /**
     * Get collection from comma-delimited ids or names
     * Note: this is synonymous to how we do it in the Pastoral Report Mapper
     *
     * @param BaseMapper $mapper
     * @param string|null $ids
     * @return GenericCollection
     */
    private function getCollection(BaseMapper $mapper, ?string $ids): GenericCollection
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
     * Find the list of students that required has or will require differentiation profile
     *
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    public function findStudentList()
    {
        $query = "{$this->getSelectString()}
                {$this->getFromString()}
                WHERE p.expires_at IS NULL AND (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
                GROUP BY p.student_id, s.lastname, s.chosen_name
                ORDER BY s.lastname, s.chosen_name;
        ";
        $statement = $this->getDatabase()->prepareQuery($query);
        $statement->execute();

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            /* No profile exists yet, let's create an empty one
             */
            if (null === $model) {
                $model = $this->createModel();
            }
            $model->setStudent($this->getJoinMapper('student')->hydrate($row, 's'));
            $collection->add($model);
        }

        return $collection;
    }

    /**
     * Apply filter for pastoral record permission based on the viewer's access level
     *
     * @param Builder $pb
     * @param User    $viewer
     *
     * @return Builder
     */
    public function filterByPermission(Builder $pb, User $viewer): Builder
    {
        if (null === $viewer->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL)) {
            $pb->rawWhere(" NOT EXISTS (
                SELECT 1 FROM `pastoral_access` pa_deny
                WHERE pa_deny.`pastoral_id` = `p`.`id`
                AND pa_deny.`deleted_at` IS NULL
                AND pa_deny.`access` = :accessDeny
                AND (
                    pa_deny.`user_id` = :viewerId OR
                    pa_deny.`role_id` = :viewerRoleId OR
                    (pa_deny.`group_id` IN (
                        SELECT `pgm`.`group_id`
                        FROM `pastoral_group` pg
                        LEFT JOIN `pastoral_group_member` pgm ON `pgm`.`group_id` = `pg`.`id` AND `pgm`.`deleted_at` IS NULL
                        WHERE `pgm`.`user_id` = :viewerId AND `pg`.`deleted_at` IS NULL
                    ))
                )
                AND `p`.`owner_id` <> :viewerId
            )", [
                'accessDeny'    => UserAccess::ACCESS_DENY,
                'viewerId'      => $viewer->getId(),
                'viewerRoleId'  => $viewer->getRole()->getId(),
            ]);

            $tempPb = $this->getPredicateBuilder()
                /* The viewer should be able to see the records they've created
                 */
                           ->equals('p.owner_id', $viewer->getId())
                /* If the pastoral access is set to the specific user
                 */
                           ->rawWhere(
                               '(`pa`.`user_id` = :viewerId AND `pa`.`access` > :accessDeny)',
                               [
                               'viewerId'   => $viewer->getId(),
                               'accessDeny' => UserAccess::ACCESS_DENY,
                               ]
                               /* Checking group-based permission
                               */
                           )->rawWhere(
                               '(
                                `pa`.`group_id` IN (
                                    SELECT `pgm`.`group_id`
                                    FROM pastoral_group pg
                                    LEFT JOIN `pastoral_group_member` pgm ON `pgm`.`group_id` = `pg`.`id`
                                    WHERE `pgm`.`user_id` = :viewerId
                                    AND `pgm`.`deleted_at` IS NULL
                                    AND `pg`.`deleted_at` IS NULL
                                )
                                AND `pa`.`access` > :accessDeny
                            )',
                               [
                               'viewerId'   => $viewer->getId(),
                               'accessDeny' => UserAccess::ACCESS_DENY,
                               ]
                           );

            /* Role-based access checks
             */
            if ($viewer->getRole()->isStaff()) {
                /* If the viewer is staff then return the records they have access to
                 */
                $tempPb->rawWhere('(
                    (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                    `pa`.`access` > :accessDeny
                 )', [
                    'viewerRoleId'   => $viewer->getRole()->getId(),
                    'viewerRoleType' => $viewer->getRole()->getType(),
                    'accessDeny'    => UserAccess::ACCESS_DENY,
                ]);
            } else if ($viewer->getRole()->isStudent()) {
                /* If the viewer is student, then return the records about them and have access to
                 */
                $tempPb->rawWhere('(
                    (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                    `p`.`student_id` = :viewerRoleId AND
                    `pa`.`access` > :accessDeny
                 )', [
                    'viewerRoleId'   => $viewer->getRole()->getId(),
                    'viewerRoleType' => $viewer->getRole()->getType(),
                    'accessDeny'    => UserAccess::ACCESS_DENY,
                ]);
            } else if ($viewer->getRole()->isParent()) {
                /* If the viewer is parent then only return the records of their children and
                 * only the ones they have access to
                 */
                (new \Schoolbox\External\User())->loadChildren([$viewer]);
                if ($viewer->getChildren()->count() > 0) {
                    $childrenIds = $viewer->getChildren()->getPropertyValues('id');
                    $childrenPlaceHolder = Builder::getArrayPlaceholders(
                        $childrenIds,
                        'parentChildId'
                    );
                    $tempPb->rawWhere(
                        "(
                        (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                        `p`.`student_id` IN $childrenPlaceHolder AND
                        `pa`.`access` > :accessDeny
                        )",
                        array_merge(
                            [
                                'viewerRoleId'   => $viewer->getRole()->getId(),
                                'viewerRoleType' => $viewer->getRole()->getType(),
                                'accessDeny'     => UserAccess::ACCESS_DENY,
                            ],
                            Builder::getArrayParameters($childrenIds, 'parentChildId')
                        )
                    );
                }
            }

            $tempPb = $this->getPredicateBuilder()->any($tempPb);

            if ($viewer->getCampusCollection()->count() > 0) {
                $campusIds         = $viewer->getCampusCollection()->getPropertyValues('id');
                $campusPlaceHolder = Builder::getArrayPlaceholders($campusIds, 'campusId');

                /* Campus based access
                 */
                $tempPb->rawWhere(
                    "(
                    `pa`.`match_campus` = 0 OR
                    (
                    `pa`.`match_campus` = 1 AND
                    `p`.`student_id` IN (
                        SELECT `userCampus`.`uid`
                        FROM `userCampus`
                        WHERE `userCampus`.`campus` IN $campusPlaceHolder
                        )
                    )
                )",
                    Builder::getArrayParameters($campusIds, 'campusId')
                );

                $pb->all($tempPb);
            }
        }
        return $pb;
    }

    public function findByFilters(
        User $viewer,
        ?array $nameSearch,
        ?array $yearLevelIds,
        ?array $campusIds,
        ?array $adjustmentCategories,
        ?array $adjustmentLevels,
        ?array $status,
        ?array $cursorParams = null,
        ?array $order = null,
        ?int $limit = null
    ): GenericCollection {
        $pb = $this->getPredicateBuilder();
        if (!empty($nameSearch)) {
            $pb = $this->buildNameSearchPredicate($pb, $nameSearch);
        }

        if (!empty($yearLevelIds)) {
            $pb = $this->buildYearLevelPredicate($pb, $yearLevelIds);
        }

        if (!empty($campusIds)) {
            $pb = $pb->in('sc.campus', $campusIds);
        }

        if (!empty($adjustmentCategories)) {
            $categoriesPlaceholders = Builder::getArrayPlaceholders($adjustmentCategories, 'adjustmentCategory');
            $pb = $pb->rawWhere("COALESCE(ac.id, :adjustment_categories_none_key) IN $categoriesPlaceholders", [
                'adjustment_categories_none_key' => DifferentiationProfile\AdjustmentCategory\KeyConverter::NONE_KEY_VALUE,
                ...Builder::getArrayParameters($adjustmentCategories, 'adjustmentCategory')
            ]);
        }

        if (!empty($adjustmentLevels)) {
            $levelsPlaceholders = Builder::getArrayPlaceholders($adjustmentLevels, 'adjustmentLevel');
            $pb = $pb->rawWhere("COALESCE(al.id, :adjustment_levels_none_key) IN $levelsPlaceholders", [
                'adjustment_levels_none_key' => DifferentiationProfile\AdjustmentLevel\KeyConverter::NONE_KEY_VALUE,
                ...Builder::getArrayParameters($adjustmentLevels, 'adjustmentLevel')
            ]);
        }

        $statusSubquery = $statusSelect = "";
        $statusParam = [];
        if (!empty($status)) {
            [$pb, $statusSelect, $statusSubquery, $statusParam] = $this->buildStatusConditions($pb, $status);
        }

        $pb->isNull('differentiation_profiles.deleted_at');

        $pb = $this->filterByPermission($pb, $viewer);

        if (!empty($cursorParams)) {
            $pb = $this->applyCursor(
                $pb,
                $cursorParams,
                $order ?? $this->getDefaultOrderByParams()
            );
        }

        $where = !empty($pb->getWhere()) ? 'AND ' . implode(' AND ', $pb->getWhere()) : '';

        /* Ensure viewer has access to view differentiation profiles
         */
        $service = UserAccess::SERVICE_DIFFERENTIATION;
        $accessLevel = UserAccess::ACCESS_WRITE;

        $param = array_merge(
            $pb->getParams(),
            [
                'service'     => $service,
                'accessLevel' => $accessLevel,
                'viewerId'    => $viewer->getId(),
            ],
            $statusParam,
        );

        $limit = null === $limit ? '' : "LIMIT {$limit}";
        $orderBy = ((null === $order) ? '' : "ORDER BY " . implode(', ', array_map(
            fn($col, $dir) => "{$col} {$dir}",
            array_keys($order),
            $order,
        )));

        $query = "{$this->getSelectString()}{$statusSelect}
                {$this->getFromString()}
                INNER JOIN userCampus sc ON s.id = sc.uid
                INNER JOIN userAccess ua ON sc.campus = ua.sid AND ua.service = :service AND ua.type >= :accessLevel AND ua.uid = :viewerId
                LEFT JOIN differentiation_profiles_adjustment_category ac ON FIND_IN_SET(ac.id, differentiation_profiles.adjustment_category)
                LEFT JOIN differentiation_profiles_adjustment_level al ON FIND_IN_SET(al.id, differentiation_profiles.adjustment_level)
                {$statusSubquery}
                WHERE (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
                {$where}
                GROUP BY p.student_id, s.lastname, s.chosen_name
                {$orderBy}
                {$limit}
        ;";

        $statement = $this->getDatabase()->executeQuery(
            $query,
            $param
        );

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $model = $this->hydrate($row);
            /* No profile exists yet, let's create an empty one
             */
            if (null === $model) {
                $model = $this->createModel();
            }
            $model->setStudent($this->getJoinMapper('student')->hydrate($row, 's'));
            $model->setCreatedBy($this->getJoinMapper('createdBy')->hydrate($row, 'createdBy'));
            $model->setUpdatedBy($this->getJoinMapper('updatedBy')->hydrate($row, 'updatedBy'));
            $model->setAdjustmentCategory($this->getCollection(AdjustmentCategories\Mapper::getInstance(), $row['adjustment_category']));
            $model->setAdjustmentLevel($this->getCollection(AdjustmentLevels\Mapper::getInstance(), $row['adjustment_level']));
            $collection->add($model);
        }

        $collection = $this->loadPastoralRecordsForCollection($collection);
        return $collection;
    }

    /**
     * Build name search predicate
     * @param Builder $pb
     * @param array $nameSearch
     * @return Builder
     */
    private function buildNameSearchPredicate(Builder $pb, array $nameSearch): Builder
    {
        $pb->in('p.student_id', $nameSearch);
        return $pb;
    }

    /**
     * Build year level predicate
     * @param Builder $pb
     * @param array $yearLevelIds
     * @return Builder
     */
    private function buildYearLevelPredicate(Builder $pb, array $yearLevelIds): Builder
    {
        $yearlevelPlaceholders = Builder::getArrayPlaceholders($yearLevelIds, 'yearLevelId');
        $pb = $pb->rawWhere("COALESCE(s.year_id, :year_levels_none_key) IN $yearlevelPlaceholders", [
            'year_levels_none_key' => DifferentiationProfile\YearLevel\KeyConverter::NONE_KEY_VALUE,
            ...Builder::getArrayParameters($yearLevelIds, 'yearLevelId')
        ]);
        return $pb;
    }

    /**
     * Build status flags subquery
     *
     * @param string $isPopulated
     * @return array
     */
    private function buildStatusFlagsSubquery(string $isPopulated): array
    {
        /* Build status flags as a derived table to avoid duplication
         */
        $statusFlagsSubquery = [
            "is_out_of_date" => "
                CASE
                    /* Out of date records
                     */
                    WHEN MAX(p.updated_at) > COALESCE(differentiation_profiles.updated_at, :farfaraway)
                    THEN 1 ELSE 0
                END as is_out_of_date
            ",
            "is_unpopulated" => "
                CASE
                    /* Differentiation profile has no fields populated
                     */
                    WHEN (NOT ({$isPopulated}))
                    THEN 1 ELSE 0
                END as is_unpopulated
            ",
            "is_expired" => "
                CASE
                    /* All pastoral records are expired
                     */
                    WHEN p.student_id NOT IN (
                        SELECT DISTINCT p2.student_id
                        FROM pastoral p2
                        LEFT JOIN lu_pastoral_type lpt2 ON p2.type_id = lpt2.id
                        LEFT JOIN lu_pastoral_subtype lps2 ON p2.subtype_id = lps2.id
                        WHERE p2.deleted_at IS NULL
                        AND (lpt2.contribute_differentiation_profile OR lps2.contribute_differentiation_profile)
                        AND COALESCE(p2.expires_at, :farfaraway) >= :today
                    )
                    THEN 1 ELSE 0
                END as is_expired
            "
        ];
        return $statusFlagsSubquery;
    }

    /**
     * Build status conditions
     *
     * @param Builder $pb
     * @param array $status
     * @return array
     */
    private function buildStatusConditions(Builder $pb, array $status): array
    {
        /* Build conditions for each status type using the computed status flags
    */
        $statusConditions = $this->getPredicateBuilder();

        /* Active records
         */
        if (array_key_exists(DifferentiationProfile\Status\Definition::KEY_INCLUDE_ACTIVE_RECORDS, $status)) {
            /* if not out of date, not unpopulated, not all linked pastoral have expired then it is considered as active
             */
            $statusConditions->all(
                $this->getPredicateBuilder()
                     ->notEquals('status_flags.is_out_of_date', 1)
                     ->notEquals('status_flags.is_unpopulated', 1)
                     ->notEquals('status_flags.is_expired', 1)
            );
        }

        /* Out of date records
         */
        if (array_key_exists(DifferentiationProfile\Status\Definition::KEY_INCLUDE_OUT_OF_DATE_RECORDS, $status)) {
            $statusConditions->equals('status_flags.is_out_of_date', 1);
        }

        /* Unpopulated records
         */
        if (array_key_exists(DifferentiationProfile\Status\Definition::KEY_INCLUDE_UNPOPULATED_RECORDS, $status)) {
            $statusConditions->equals('status_flags.is_unpopulated', 1);
        }

        /* Expired records
         */
        if (array_key_exists(DifferentiationProfile\Status\Definition::KEY_INCLUDE_EXPIRED_RECORDS, $status)) {
            $statusConditions->equals('status_flags.is_expired', 1);
        }

        if (!empty($statusConditions->getWhere())) {
            $pb->any($statusConditions);
        }

        $today = \Alaress_Date_Format::now()->getDatabaseDate();

        $tempPb = $this->getPredicateBuilder();

        foreach (self::CONFIG_METHOD_MAPPING as $configKey => $methodName) {
            $key = "differentiation_profiles.$configKey";
            $tempPb = $tempPb->All(
                $this->getPredicateBuilder()->notNull($key)
                     ->notEquals($key, '')
            );
        }
        $isPouplated = implode(' OR ', $tempPb->getWhere());

        $statusFlagsSubquery = $this->buildStatusFlagsSubquery($isPouplated);

        $statusFlagsSubquerySelect = "
                SELECT
                    p.student_id,
                    " . implode(", ", $statusFlagsSubquery) . "
                FROM differentiation_profiles
                RIGHT JOIN pastoral p ON differentiation_profiles.student_id = p.student_id AND differentiation_profiles.deleted_at IS NULL
                LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
                LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
                WHERE (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile) AND p.deleted_at IS NULL
                GROUP BY p.student_id
            ";

        $statusSelect = ", status_flags.*";
        $statusSubquery = "LEFT JOIN ({$statusFlagsSubquerySelect}) status_flags ON status_flags.student_id = p.student_id";
        $statusParam = [
            'farfaraway' => self::FAR_FAR_AWAY_FULL_DATE,
            'today'     => $today,
            ...$tempPb->getParams(),
        ];
        return [$pb, $statusSelect, $statusSubquery, $statusParam];
    }

    private function applyCursor(Builder $pb, $cursorParams, $order): Builder
    {
        /* Build up the cursor predicates
         * This is synonymous to how we do it in Alaress\Database\Cursor\MapperCursor
         */
        $columnBuilders         = [];
        $previousColumnBuilders = [];
        foreach ($order as $column => $direction) {
            $value = $cursorParams[$column] ?? null;
            $parameterisedValue = ":cursor_" . str_replace('.', '_', $column);
            $coalesceValue = $this->getCoalesceValueForColumn($column);

            if ($direction === self::SORT_ORDER_ASC) {
                $thisBuilder = $pb->emptyClone()->rawWhere(
                    "COALESCE({$column}, :coalesceValue) > COALESCE($parameterisedValue, :coalesceValue)",
                    [
                        $parameterisedValue => $value,
                        "coalesceValue"   => $coalesceValue
                    ]
                );
            } else if ($direction === self::SORT_ORDER_DESC) {
                $thisBuilder = $pb->emptyClone()->rawWhere(
                    "COALESCE({$column}, :coalesceValue) < COALESCE($parameterisedValue, :coalesceValue)",
                    [
                        $parameterisedValue => $value,
                        "coalesceValue"   => $coalesceValue
                    ]
                );
            } else {
                continue;
            }

            $columnBuilder = $pb->emptyClone();
            foreach ($previousColumnBuilders as $previousColumnBuilder) {
                $columnBuilder = $columnBuilder->all($previousColumnBuilder);
            }
            $columnBuilders[] = $columnBuilder->all($thisBuilder);

            $previousColumnBuilders[] = $pb->emptyClone()->rawWhere(
                "COALESCE({$column}, :coalesceValue) = COALESCE($parameterisedValue, :coalesceValue)",
                [
                    $parameterisedValue => $value,
                    "coalesceValue"   => $coalesceValue
                ]
            );
        }

        // Put each column builder into the main predicate builder
        $subBuilder = $pb->emptyClone();
        foreach ($columnBuilders as $columnBuilder) {
            // All the builders in the column builder must match
            $subBuilder = $subBuilder->all($columnBuilder);
        }
        // Any of the column builders may match
        $pb = $pb->any($subBuilder);
        return $pb;
    }

    /**
     * Get coalesce value for a given column
     *
     * @param string $column
     * @return string|int
     */
    private function getCoalesceValueForColumn(string $column): string|int
    {
        return match ($column) {
            Cursor::CURSOR_ORDER_BY_UPDATED_AT_COLUMN => self::FAR_FAR_AWAY_PAST,
            Cursor::CURSOR_ORDER_BY_STUDENT_ID_COLUMN => 0,
            default => "",
        };
    }

    /**
     * Load pastoral records for the students in the collection and set to the differentiation profile
     *
     * @param GenericCollection $profiles
     * @return GenericCollection
     */
    public function loadPastoralRecordsForCollection(GenericCollection $profiles): GenericCollection
    {
        /** No profiles in the collection, no need to query pastoral records
         */
        if ($profiles->count() === 0) {
            return $profiles;
        }

        $studentIds = [];
        foreach ($profiles as $profile) {
            $studentIds[] = $profile->getStudent()->getId();
        }

        $pastoralMapper = PastoralMapper::getInstance();
        $pb = $pastoralMapper->getPredicateBuilder();
        $pb->in('student_id', $studentIds);
        $pb->any(
            $pb->emptyClone()
               ->isTrue('t.contribute_differentiation_profile')
               ->isTrue('c.contribute_differentiation_profile')
        );
        $pastoralRecords = $pastoralMapper
            ->findByPredicates($pb)
            ->groupByCallback(function (PastoralModel $v) {
                return $v->getStudent()->getId();
            });

        foreach ($profiles as $profile) {
            $studentId = $profile->getStudent()->getId();
            if (isset($pastoralRecords[$studentId])) {
                $profile->setPastoralRecords($pastoralRecords[$studentId]);
            }
        }

        return $profiles;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "SELECT {$this->getColumnString()},
                {$this->getJoinColumnString('student', 's')},
                {$this->getJoinColumnString('createdBy', 'createdBy')},
                {$this->getJoinColumnString('updatedBy', 'updatedBy')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM pastoral p
            LEFT JOIN differentiation_profiles ON differentiation_profiles.student_id = p.student_id AND
                                                  differentiation_profiles.deleted_at IS NULL
            LEFT JOIN `user` AS `createdBy`ON differentiation_profiles.created_by = `createdBy`.`id`
            LEFT JOIN `user` AS `updatedBy`ON differentiation_profiles.updated_by = `updatedBy`.`id`
            LEFT JOIN pastoral_access pa ON p.id = pa.pastoral_id
            INNER JOIN user s ON p.student_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
            LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return [
            's.lastname' => 'ASC',
            's.chosen_name' => 'ASC',
            'p.student_id' => 'ASC',
        ];
    }
}
