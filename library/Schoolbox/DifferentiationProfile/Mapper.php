<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\DifferentiationProfile;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Filter\Filters\DifferentiationProfile;
use Schoolbox\Filter\Filters\DifferentiationProfile\Name\Definition;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 *
 * @package Schoolbox\DifferentiationProfile
 */
class Mapper extends BaseMapper
{
    public const int DEFAULT_LIMIT = 20;
    /**
     * Setup mapper
     */
    protected function mapping(): void
    {
        $this->hasTable('differentiation_profiles');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->uses('adjustment_category', self::TYPE_STRING);
        $this->uses('adjustment_level', self::TYPE_STRING);
        $this->uses('overview', self::TYPE_STRING);
        $this->uses('interests_strengths', self::TYPE_STRING);
        $this->uses('needs_challenges', self::TYPE_STRING);
        $this->uses('strategies_adjustments', self::TYPE_STRING);
        $this->uses('goals', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
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

    public function findByFilters(
        User $viewer,
        ?array $nameSearch,
        ?array $yearLevelIds,
        ?array $campusIds,
        ?array $adjustmentCategories,
        ?array $adjustmentLevels,
        ?array $cursorParams = null,
        ?array $order = null,
        ?int $limit = null
    ): GenericCollection {
        $pb = $this->getPredicateBuilder();
        if (!empty($nameSearch)) {
            // Just use the first one, and split it into tokens
            foreach ($nameSearch as $nameSearchString => $_) {
                $names = array_map('trim', preg_split('/\\s+/', (string)$nameSearchString));
                foreach ($names as $name) {
                    if (strlen($name) > 0 && $name !== Definition::KEY_ALL) {
                        $pb = $pb->any(
                            $this->getPredicateBuilder()->like(
                                's.chosen_name',
                                '%' . $this->getDatabase()->getQuoter()->escapeForLike($name) . '%'
                            )->like(
                                's.lastname',
                                '%' . $this->getDatabase()->getQuoter()->escapeForLike($name) . '%'
                            )
                        );
                    }
                }
            }
        }

        if (!empty($yearLevelIds)) {
            $pb = $pb->in('s.year_id', $yearLevelIds);
        }

        if (!empty($campusIds)) {
            $pb = $pb->in('sc.campus', $campusIds);
        }

        if (!empty($adjustmentCategories)) {
            $categories = implode(',', $adjustmentCategories);
            $pb = $pb->rawWhere("COALESCE(ac.id, :adjustment_categories_none_key) IN ( $categories )", [
                'adjustment_categories_none_key' => DifferentiationProfile\AdjustmentCategory\KeyConverter::NONE_KEY_VALUE,
            ]);
        }

        if (!empty($adjustmentLevels)) {
            $levels = implode(',', $adjustmentLevels);
            $pb = $pb->rawWhere("COALESCE(al.id, :adjustment_levels_none_key) IN ( $levels )", [
                'adjustment_levels_none_key' => DifferentiationProfile\AdjustmentLevel\KeyConverter::NONE_KEY_VALUE,
            ]);
        }

        $pb->isNull('differentiation_profiles.deleted_at');

        $pb->any(
            $this->getPredicateBuilder()
            ->isNull('p.expires_at')
            ->greaterThan('p.expires_at', \Alaress_Date_Format::now()->getDatabaseDate())
        );

        if (!empty($cursorParams)) {
            $pb = $this->applyCursor(
                $pb,
                $cursorParams,
                $order ?? $this->getDefaultOrderByParams()
            );
        }

        $where = implode(' AND ', $pb->getWhere());

        /* Ensure viewer has access to view differentiation profiles
         */
        $service = UserAccess::SERVICE_DIFFERENTIATION_PROFILE;
        $accessLevel = UserAccess::ACCESS_WRITE;

        $param = array_merge(
            $pb->getParams(),
            [
                'service'     => $service,
                'accessLevel' => $accessLevel,
                'viewerId'    => $viewer->getId(),
            ]
        );

        $limit = null === $limit ? '' : "LIMIT {$limit}";
        $orderBy = "ORDER BY " . ((null === $order) ? '' : implode(', ', array_map(
            fn($col, $dir) => "{$col} {$dir}",
            array_keys($order),
            $order,
        )) . ',') . ' p.student_id asc';

        $query = "{$this->getSelectString()}
                {$this->getFromString()}
                INNER JOIN userCampus sc ON s.id = sc.uid
                INNER JOIN userAccess ua ON sc.campus = ua.sid AND ua.service = :service AND ua.type >= :accessLevel AND ua.uid = :viewerId
                LEFT JOIN differentiation_profiles_adjustment_category ac ON FIND_IN_SET(ac.id, differentiation_profiles.adjustment_category)
                LEFT JOIN differentiation_profiles_adjustment_level al ON FIND_IN_SET(al.id, differentiation_profiles.adjustment_level)
                WHERE (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
                AND {$where}
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
            $collection->add($model);
        }

        return $collection;
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
            if (!isset($value)) {
                continue;
            }

            if ($direction === self::SORT_ORDER_ASC) {
                $thisBuilder = $pb->emptyClone()->greaterThan($column, $value);
            } else if ($direction === self::SORT_ORDER_DESC) {
                $thisBuilder = $pb->emptyClone()->lessThan($column, $value);
            } else {
                continue;
            }

            $columnBuilder = $pb->emptyClone();
            foreach ($previousColumnBuilders as $previousColumnBuilder) {
                $columnBuilder = $columnBuilder->all($previousColumnBuilder);
            }
            $columnBuilders[] = $columnBuilder->all($thisBuilder);

            $previousColumnBuilders[] = $pb->emptyClone()->equals($column, $value);
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

    protected function getSelectString(): string
    {
        return "SELECT {$this->getColumnString()}, {$this->getJoinColumnString('student', 's')}";
    }

    protected function getFromString(): string
    {
        return "
            FROM differentiation_profiles
            RIGHT JOIN pastoral p ON differentiation_profiles.student_id = p.student_id AND differentiation_profiles.deleted_at IS NULL AND p.deleted_at IS NULL
            INNER JOIN user s ON p.student_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
            LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
        ";
    }

    protected function getDefaultOrderByParams(): array
    {
        return [
            's.lastname' => 'ASC',
            's.chosen_name' => 'ASC',
            'p.student_id' => 'ASC',
        ];
    }
}
