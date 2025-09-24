<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Model as Report;
use Schoolbox\Pastoral\SubType\Model as SubType;

class Helper
{
    private array $filters = ['pastoral.deleted_at IS NULL'];
    private array $params = [];

    /**
     * @param array             $report
     * @param PastoralMapper    $pastoralMapper
     * @param IConfigLoader     $configLoader
     * @param User              $user
     * @param AccessLevelCensor $accessLevelCensor
     */
    public function __construct(
        private array $report,
        private PastoralMapper $pastoralMapper,
        private IConfigLoader $configLoader,
        private User $user,
        private AccessLevelCensor $accessLevelCensor,
    ) {
        $this->setFilters();
    }

    /**
     * @return IConfigLoader
     */
    public function getConfigLoader(): IConfigLoader
    {
        return $this->configLoader;
    }

    /**
     * This will return the type that doesn't have subtypes
     *
     * @return Collection
     */
    public function getType(): Collection
    {
        /** @param Collection $types */
        $types = $this->report['types'];
        /** @param Collection|null $subtypes */
        $subtypes = $this->report['subtypes'];
        return $types->diff(
            $subtypes?->map(
                fn(SubType $subType) => $subType->getPastoralType()
            )
        );
    }

    /**
     * This will return the subtypes that the report is filtering to
     *
     * @return Collection
     */
    public function getSubTypes(): Collection
    {
        return $this->report['subtypes'] ?? new Collection();
    }

//    private function poupulateTypeSubTypeIfNull(): void
//    {
//        if (null === $this->report['types']) {
//            $this->report['types'] = $this->pastoralMapper->getTypeCollection();
//        }
//        if (null === $this->report['subtypes']) {
//            $this->report['subtypes'] = $this->pastoralMapper->getSubTypeCollection();
//        }
//    }
    /**
     * Date range filter
     *
     * @return void
     */
    private function getWhereDate(): void
    {
        if ($this->report['beforeDate'] instanceof Alaress_Date_Format) {
            $this->filters[] = "(COALESCE(`pastoral`.`occurred_on`, `pastoral`.`created_at`) <= :beforeDate)";
            $this->params['beforeDate'] = $this->report['beforeDate']->endOfDay()->toIso8601String();
        }

        if ($this->report['afterDate'] instanceof Alaress_Date_Format) {
            $this->filters[] = "(COALESCE(`pastoral`.`occurred_on`, `pastoral`.`created_at`) >= :afterDate)";
            $this->params['afterDate'] = $this->report['afterDate']->startOfDay()->toIso8601String();
        }
    }
    /**
     * Type and Subtype filter
     *
     * @return void
     */
    private function getWhereInTypeSubType(): void
    {
        $whereInTypeSubTypeQuery = $whereInTypeSubTypeParams = [];
        if ($this->getType()->count() > 0) {
            $typeIds = $this->getType()->getPropertyValues('id');
            $whereTypeIds = Builder::getArrayPlaceholders(
                $typeIds,
                'pastoralTypeId',
            );
            $whereInTypeSubTypeQuery[] = "pastoral.type_id IN {$whereTypeIds}";
            $whereInTypeSubTypeParams = Builder::getArrayParameters(
                $typeIds,
                'pastoralTypeId',
            );
        }
        if ($this->getSubTypes()->count() > 0) {
            $subTypeIds = $this->getSubTypes()->getPropertyValues('id');
            $whereSubTypeIds = Builder::getArrayPlaceholders(
                $subTypeIds,
                'pastoralSubTypeId',
            );
            $whereInTypeSubTypeQuery[] = "pastoral.subtype_id IN {$whereSubTypeIds}";
            $whereInTypeSubTypeParams = array_merge(
                $whereInTypeSubTypeParams,
                Builder::getArrayParameters(
                    $subTypeIds,
                    'pastoralSubTypeId',
                )
            );
        }

        $query = implode(' OR ', array_filter($whereInTypeSubTypeQuery));
        if ($query) {
            $this->filters[] = "({$query})";
            $this->params = array_merge($this->params, $whereInTypeSubTypeParams);
        }
    }

    /**
     * Severity filter
     * @return void
     */
    private function getSeverity(): void
    {
        if ($this->report['severity'] && $this->configLoader->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
            $this->filters[] = "`sv`.`sequence` >= :severity";
            $this->params['severity'] = $this->report['severity']->getSequence();
        }
    }

    /**
     * Students filter
     *
     * @return void
     */
    private function getStudents(): void
    {
        if ($this->report['students']->count() > 0) {
            $this->filters[] = "pastoral.student_id IN (" .
                implode(
                    ',',
                    $this->report['students']->getPropertyValues('id')
                ) . ")";
        }
    }

    /**
     * Year level filters
     *
     * @return void
     */
    private function getYears(): void
    {
        if ($this->report['includeStudentsWithoutYear']) {
            $filters[] = "`s`.`year_id` IS NULL";
        }

        if ($this->report['years']->count() > 0) {
            $filters[] = '`s`.`year_id` IN (' .
                implode(',', $this->report['years']->getPropertyValues('id')) .
                ')';
        }

        $query = implode(' OR ', array_filter($filters ?? []));

        if ($query) {
            $this->filters[] = "({$query})";
        }
    }

    /**
     * House filters
     *
     * @return void
     */
    private function getHouses(): void
    {
        if ($this->report['includeStudentsWithoutHouse']) {
            $filters[] = "`s`.`house` IS NULL";
        }

        if ($this->report['houses']->count() > 0) {
            $filters[] = '`s`.`house` IN (' .
                implode(',', $this->report['houses']->getPropertyValues('id')) .
                ')';
        }

        $query = implode(' OR ', array_filter($filters ?? []));

        if ($query) {
            $this->filters[] = "({$query})";
        }
    }

    /**
     * Campus filters
     *
     * @return void
     */
    private function getCampuses(): void
    {
        if ($this->report['campuses']->count() > 0) {
            $this->filters[] = '`v_sc`.`campus` IN (' .
                implode(',', $this->report['campuses']->getPropertyValues('id')) .
                ')';
        }
    }

    /**
     * Author filters
     *
     * @return void
     */
    private function getAuthors(): void
    {
        if ($this->report['authors']->count() > 0) {
            $this->filters[] = "pastoral.author_id IN (" .
                implode(',', $this->report['authors']->getPropertyValues('id')) .
                ")";
        }
    }

    /**
     * @return bool
     */
    private function shouldFilterAction(): bool
    {
        return $this->configLoader->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED) &&
            (
                ($this->report['actionStatus'] ?? false) ||
                $this->report['includeRecordsWithoutAction'] ||
                $this->report['actions']->count() > 0
            );
    }

    /**
     * Action filters
     *
     * @return void
     */
    private function getActions(): void
    {
        if (!$this->shouldFilterAction()) {
            return;
        }

        $subFilter = [];
        $value = null;
        if ($this->report['actions']->count() > 0) {
            $subFilter[] = '`a`.`action_id` IN (' .
                implode(',', $this->report['actions']->getPropertyValues('id')) .
                ')';
        }

        if ($this->report['actions']->count() > 0 || !$this->report['includeRecordsWithoutAction']) {
            if ($this->report['actionStatus'] ?? false) {
                $subFilter[] = '`a`.`completed` = :actionStatus';
                $value = (int)(
                    $this->report['actionStatus'] === \Schoolbox\Pastoral\Report\Model::ACTION_STATUS_COMPLETED
                );
            }
        }

        $subFilter = array_filter($subFilter);
        if (!empty($subFilter)) {
            $filters[] = '(' . implode(' AND ', $subFilter) . ')';
        }

        if ($this->report['includeRecordsWithoutAction']) {
            $filters[] = "`a`.`action_id` IS NULL";
        }

        $query = implode(' OR ', array_filter($filters ?? []));

        if ($query) {
            $this->filters[] = "({$query})";
            if (null !== $value) {
                $this->params['actionStatus'] = $value;
            }
        }
    }

    /**
     * Access filters
     *
     * @return void
     */
    private function getAccess(): void
    {
        $user = $this->report['user'];
        if ($this->report['involved'] || !$this->pastoralMapper->isUserInPastoralService($user)) {
            $this->filters[] = "
                (
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `role_id` = :user_role_id_0 AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `user_id` = :user_id_0      AND `pastoral_id` = `pastoral`.`id`) > 0 OR
                    (
                        SELECT COUNT(*) FROM `pastoral_access` WHERE `group_id` IN (
                            SELECT `group_id`
                            FROM `pastoral_group_member`
                            WHERE `user_id` = :user_id_1
                        ) AND `pastoral_id` = `pastoral`.`id`
                    ) > 0 OR
                    `pastoral`.`owner_id` = :user_id_2
                )
            ";
            $this->params['user_role_id_0'] = $user->getRole()->getId();
            $this->params['user_id_0']      = $user->getId();
            $this->params['user_id_1']      = $user->getId();
            $this->params['user_id_2']      = $user->getId();
        }
    }

    /**
     * Record expiry filters
     *
     * @return void
     */
    private function getExpired(): void
    {
        if (!$this->report['includeArchivedRecords']) {
            $this->filters[] = "(`pastoral`.`expires_at` >= CURDATE() OR `pastoral`.`expires_at` IS NULL)";
        }
    }

    /**
     * Tag filters
     *
     * @return void
     */
    private function getTags(): void
    {
        if ($this->report['tags']->count() > 0) {
            $this->filters[] = '`t`.`tag_id` IN (' .
                implode(',', $this->report['tags']->getPropertyValues('id')) .
                ')';
        }
    }

    private function getPermission(): void
    {
        /** @var User $viewer */
        $viewer = $this->report['user'];
        /* Should not filter records if the viewer is the Pastoral Moderator
         */
        if (null !== $viewer->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL)) {
            return;
        }

        /* If any of the pastoral access is set to deny for the viewer, then the record should not
         * be included, unless the record belongs to the user
         */
        $this->filters[] = "
        NOT EXISTS (
            SELECT 1 FROM `pastoral_access` pa_deny
            WHERE pa_deny.`pastoral_id` = `pastoral`.`id`
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
            AND `pastoral`.`owner_id` <> :viewerId
        )";

        $filters = [];
        /* The viewer should be able to see the records they've created
         */
        $filters[] = "(`pastoral`.`owner_id` = :viewerId)";

        /* If the pastoral access is set to the specific user
         */
        $filters[] = "(`pa`.`user_id` = :viewerId AND `pa`.`access` > :accessDeny)";

        /* Checking for role-based permission
         */
        if ($viewer->getRole()->isStaff()) {
            /* If the viewer is staff then return the records they have access to
             */
            $filters[] = "
            (
                (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                `pa`.`access` > :accessDeny
             )";
        } else if ($viewer->getRole()->isStudent()) {
            /* If the viewer is student, then return the records about them and have access to
             */
            $filters[] = "
            (
                (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                `pastoral`.`student_id` = :viewerRoleId AND
                `pa`.`access` > :accessDeny
             )";
        } else {
            /* If the viewer is parent then only return the records of their children and
             * only the ones they have access to
             */
            (new \Schoolbox\External\User())->loadChildren([$viewer]);
            $childrenIds = implode(
                ',',
                $viewer->getChildren()->getPropertyValues('id')
            );
            $filters[] = "
            (
                (`pa`.`role_id` = :viewerRoleId OR `pa`.`role_type` = :viewerRoleType) AND
                `pastoral`.`student_id` IN ($childrenIds) AND
                `pa`.`access` > :accessDeny
            )";
        }

        /* Checking group-based permission
         */
        $filters[] = "
            (
                `pa`.`group_id` IN (
                    SELECT `pgm`.`group_id`
                    FROM pastoral_group pg
                    LEFT JOIN `pastoral_group_member` pgm ON `pgm`.`group_id` = `pg`.`id`
                    WHERE `pgm`.`user_id` = :viewerId
                    AND `pgm`.`deleted_at` IS NULL
                    AND `pg`.`deleted_at` IS NULL
                )
                AND `pa`.`access` > :accessDeny
            )";

        /* Same campus validation
         */
        $campusIds = $viewer->getCampusCollection()->getPropertyValues('id');
        $sameCampus = "(
            `pa`.`match_campus` = 0 OR
            (
            `pa`.`match_campus` = 1 AND
            `pastoral`.`student_id` IN (
                SELECT `userCampus`.`uid`
                FROM `userCampus`
                WHERE `userCampus`.`campus` IN (" . implode(',', $campusIds) . ")
                )
            )
        )";

        $this->params['viewerId'] = $viewer->getId();
        $this->params['accessDeny'] = PastoralAccessMapper::ACCESS_DENY;
        $this->params['viewerRoleId'] = $viewer->getRole()->getId();
        $this->params['viewerRoleType'] = $viewer->getRole()->getType();
        $this->filters[] = "(" . implode(' OR ', $filters) . " AND {$sameCampus} )";
    }

    /**
     * Initiates all the filters
     *
     * @return void
     */
    private function setFilters(): void
    {
        $this->getWhereDate();
        $this->getWhereInTypeSubType();
        $this->getSeverity();
        $this->getStudents();
        $this->getYears();
        $this->getHouses();
        $this->getCampuses();
        $this->getAuthors();
        $this->getActions();
        $this->getAccess();
        $this->getExpired();
        $this->getTags();
        $this->getPermission();
    }

    /**
     * Get the FROM string of the query
     *
     * @return string
     */
    public function getFromString(): string
    {

        $fromString = "
            FROM {$this->pastoralMapper->getTable()}
            INNER JOIN user s ON pastoral.student_id = s.id AND s.deleted_at IS NULL
            LEFT JOIN pastoral_access pa ON pastoral.id = pa.pastoral_id
            LEFT JOIN lu_pastoral_severity sv ON pastoral.severity_id = sv.id
            LEFT JOIN userCampus v_sc ON s.id = v_sc.uid
        ";

        if ($this->shouldFilterAction()) {
            $joinType = $this->report['includeRecordsWithoutAction'] ? 'LEFT' : 'INNER';
            $fromString .= "
                {$joinType} JOIN pastoral_record_action a ON pastoral.id = a.pastoral_id AND a.deleted_at IS NULL
            ";
        }

        if ($this->report['tags']->count() > 0) {
            $fromString .= "
                INNER JOIN pastoral_tags g on pastoral.id = g.pastoral_id
            ";
        }

        return $fromString;
    }

    /**
     * Get all the filters from the report setting
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get all the parameters for filtering
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get the order preference set in the report setting
     *
     * @return array
     */
    public function getReportOrder(): array
    {
        $orderParts = [];
        foreach ($this->report['orders'] as $orderCurrent) {
            switch ($orderCurrent) {
                case Report::ORDER_STUDENT:
                    $orderParts[] = [
                        'column' => "s.lastname"
                    ];
                    $orderParts[] = [
                        'column' => "s.chosen_name"
                    ];
                    break;
                case Report::ORDER_SEVERITY:
                    $orderParts[] = [
                        'column' => "sv.sequence"
                    ];
                    break;
                case Report::ORDER_CREATED_AT:
                    $orderParts[] = [
                        'column' => "pastoral.created_at",
                        'direction' => 'DESC'
                    ];
                    break;
                case Report::ORDER_OCCURRED_ON:
                    $orderParts[] = [
                        'column' => "pastoral.occurred_on",
                        'direction' => 'DESC'
                    ];
                    break;
                case Report::ORDER_TYPE:
                    $orderParts[] = [
                        'column' => "t.name"
                    ];
                    break;
                case Report::ORDER_SUBTYPE:
                    $orderParts[] = [
                        'column' => "c.name"
                    ];
                    break;
                case Report::ORDER_POINTS:
                    $orderParts[] = [
                        'column' => "pastoral.points"
                    ];
                    break;
                case Report::ORDER_YEAR:
                    $orderParts[] = [
                        'column' => "sy.sequence",
                        'direction' => 'ASC'
                    ];
                    break;
                case Report::ORDER_AUTHOR:
                    $orderParts[] = [
                        'column' => "o.lastname"
                    ];
                    $orderParts[] = [
                        'column' => "o.chosen_name"
                    ];
                    break;
                case Report::ORDER_HOUSE:
                    $orderParts[] = [
                        'column' => "s.house"
                    ];
                    break;
                case Report::ORDER_CAMPUS:
                    $orderParts[] = [
                        'column' => "sc.name"
                    ];
                    break;
            }
        }

        $orderParts[] = ['column' => 'pastoral.id'];

        return $orderParts;
    }

    public function getStartDate(): ?Alaress_Date_Format
    {
        return $this->report['afterDate'] ?? null;
    }

    public function getEndDate(): ?Alaress_Date_Format
    {
        return $this->report['beforeDate'] ?? null;
    }

    public function getMonthsInRange(
        ?Alaress_Date_Format $startDate,
        ?Alaress_Date_Format $endDate,
        string $format,
    ): Collection {
        /* Return empty collection if either date is null.
         */
        if (null === $startDate || null === $endDate) {
            return new Collection();
        }

        $start = (clone $startDate)->startOfMonth();

        /* If the current month is in the future, skip it to the current month
         * This is to avoid showing future months in the report
         */
        if ($endDate->startOfMonth()->gt((Alaress_Date_Format::now())->startOfMonth())) {
            $current = (Alaress_Date_Format::now())->startOfMonth();
        } else {
            $current = $endDate->startOfMonth();
        }

        $months = [];
        while ($current->gte($start)) {
            array_unshift($months, strtolower($current->getUsingFormat($format)));
            $current->addMonths(-1);
        }
        return new Collection($months);
    }

    /**
     * @param Alaress_Date_Format|null $startDate
     * @param Alaress_Date_Format|null $endDate
     * @param string                   $format
     * @return Collection
     */
    public function getWeeksInRange(
        ?Alaress_Date_Format $startDate,
        ?Alaress_Date_Format $endDate,
        string $format,
    ): Collection {
        /* Return empty collection if either date is null.
         */
        if (null === $startDate || null === $endDate) {
            return new Collection();
        }
        $weekStartDayOfWeek = $this->configLoader->get('calendar_start_day');

        $start = clone $startDate;
        $end = clone $endDate;

        $weeks = [];
        /* If the current week is in the future, skip it to the current date
         * This is to avoid showing future weeks in the report
         */
        if ($end->endOfDay()->gt((Alaress_Date_Format::now())->endOfDay())) {
            $current = Alaress_Date_Format::now();
        } else {
            $current = $end;
        }
        $index = 0;
        while ($current->gte($start)) {
            if ($current->getWeekDayNumber() !== $weekStartDayOfWeek) {
                // Adjust current to the start of the week
                $weekStart = (clone $current)->previous((int)$weekStartDayOfWeek);
            } else {
                $weekStart = clone $current;
            };
            $weekEnd = (clone $weekStart)->addDays(6);

            // Ensure weekStart and weekEnd are within the original range
            $rangeStart = clone $startDate;
            $rangeEnd = clone $endDate;
            $actualStart = $weekStart->lt($rangeStart) ? $rangeStart : $weekStart;
            $actualEnd = $weekEnd->gt($rangeEnd) ? $rangeEnd : $weekEnd;

            $key = sprintf($format, $index++);
            array_unshift(
                $weeks,
                [
                    'start' => $actualStart->startOfDay(),
                    'end' => $actualEnd->endOfDay(),
                    'key' => $key,
                    /* We are hardcoding the format here as it is going to be a nightmare trying to shorten the
                     * format by removing the first year, as it's hard to predict what the format is going to be.
                     * This will make things a lot easier.
                     */
                    'label' => $actualStart->getUsingFormat('j M') . ' - ' .
                        $actualEnd->getUsingFormat('j M, Y'),
                ]
            );
            $current->addWeeks(-1);
        }
        return new Collection($weeks);
    }

    /**
     * Build cursor conditions based on the report order setting for lexicographic comparison
     *
     * @param array  $cursor
     * @param int    $minNumericValue
     * @param string $minDateValue
     * @return array
     */
    public function buildCursorConditions(array $cursor, int $minNumericValue, string $minDateValue): array
    {
        $conditions = [];
        $params = [];

        $fieldMap = [
            Report::ORDER_CREATED_AT => [
                'condition' => 'pastoral.created_at',
                'param' => $cursor['created'],
                'direction' => 'DESC',
            ],
            /* When the occurred_on is null, we should treat it as the minimum date value
             * which should be the earliest date possible (1970-01-01)
             */
            Report::ORDER_OCCURRED_ON => [
                'condition' => "COALESCE(pastoral.occurred_on, '{$minDateValue}')",
                'param' => $cursor['occurred'] ?? $minDateValue,
                'direction' => 'DESC',
            ],
            Report::ORDER_STUDENT_LASTNAME => [
                'condition' => 's.lastname',
                'param' => $cursor['student']['last']
            ],
            Report::ORDER_STUDENT_CHOSEN_NAME => [
                'condition' => 's.chosen_name',
                'param' => $cursor['student']['chosen']
            ],
            Report::ORDER_TYPE => [
                'condition' => 't.name',
                'param' => $cursor['type']
            ],
            Report::ORDER_SUBTYPE => [
                'condition' => "COALESCE(c.name, '')",
                'param' => $cursor['subtype'] ?? '',
            ],
            Report::ORDER_AUTHOR_LASTNAME => [
                'condition' => 'o.lastname',
                'param' => $cursor['author']['last']
            ],
            Report::ORDER_AUTHOR_CHOSEN_NAME => [
                'condition' => 'o.chosen_name',
                'param' => $cursor['author']['chosen']
            ],
            Report::ORDER_POINTS => [
                'condition' => "COALESCE(pastoral.points, {$minNumericValue})",
                'param' => $cursor['points'] ?? $minNumericValue,
            ],
            Report::ORDER_YEAR => [
                'condition' => "COALESCE(sy.sequence, {$minNumericValue})",
                'param' => $cursor['yearLevel'] ?? $minNumericValue,
            ],
            Report::ORDER_HOUSE => [
                'condition' => "COALESCE(s.house, {$minNumericValue})",
                'param' => $cursor['house'] ?? $minNumericValue,
            ],
            Report::ORDER_CAMPUS => [
                'condition' => "COALESCE(sc.name, '')",
                'param' => $cursor['campus'] ?? '',
            ],
            Report::ORDER_SEVERITY => [
                'condition' => "COALESCE(sv.sequence, {$minNumericValue})",
                'param' => $cursor['severity'] ?? $minNumericValue,
            ],
            Report::ORDER_PASTORAL_ID => [
                'condition' => 'pastoral.id',
                'param' => $cursor['id']
            ],
        ];
        /* Should organise the condition comparison based on the report's order settings
         */
        $sortFields = $this->report['orders']->getAll();
        $sortFields[] = Report::ORDER_PASTORAL_ID; // Always add ID as the last sorting field

        /* Flattening the fields that have sub-fields (student and author)
         * so that we can treat them as individual fields in the lexicographic comparison
         */
        $flattenedFields = [];
        $toBeFlattened = [
            Report::ORDER_STUDENT => [
                Report::ORDER_STUDENT_LASTNAME,
                Report::ORDER_STUDENT_CHOSEN_NAME,
            ],
            Report::ORDER_AUTHOR => [
                Report::ORDER_AUTHOR_LASTNAME,
                Report::ORDER_AUTHOR_CHOSEN_NAME,
            ]
        ];
        foreach ($sortFields as $field) {
            if (array_key_exists($field, $toBeFlattened)) {
                foreach ($toBeFlattened[$field] as $flattenedField) {
                    $flattenedFields[] = $flattenedField;
                }
            } else {
                $flattenedFields[] = $field;
            }
        }

        // Build lexicographic comparison conditions
        for ($i = 0; $i < count($flattenedFields); $i++) {
            $condition = [];

            // Add equality conditions for all previous fields
            for ($j = 0; $j < $i; $j++) {
                $field = $flattenedFields[$j];
                $dbField = $fieldMap[$field]['condition'] ?? $field;
                $condition[] = "{$dbField} = :cursor_{$field}";
            }

            // Add comparison for current field
            $field = $flattenedFields[$i];
            $dbField = $fieldMap[$field]['condition'] ?? $field;
            $operator = (Base::ORDER_DIRECTION_DESC === ($fieldMap[$field]['direction'] ?? Base::ORDER_DIRECTION_ASC)) ?
                '<' :
                '>';
            $condition[] = "{$dbField} {$operator} :cursor_{$field}";

            $conditions[] = '(' . implode(' AND ', $condition) . ')';
            $params["cursor_{$field}"] = $fieldMap[$field]['param'] ?? null;
        }
        return !empty($conditions) ? [
            'conditions' => $conditions,
            'params' => $params
        ] : [];
    }

    /**
     * Help filtering the records according to the user's access
     *
     * @param Collection $records
     * @return Collection
     */
    public function filterRecordsForUser(Collection $records): Collection
    {
        return $this->accessLevelCensor->filterRecordsForUser($records, $this->user);
    }
}
