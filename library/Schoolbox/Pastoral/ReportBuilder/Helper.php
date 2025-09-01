<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
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
     * @param array          $report
     * @param PastoralMapper $pastoralMapper
     * @param IConfigLoader  $configLoader
     */
    public function __construct(
        private array $report,
        private PastoralMapper $pastoralMapper,
        private IConfigLoader $configLoader,
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
        $viewer = $this->report['user'];
        /* Should not filter records if the viewer is the Pastoral Moderator
         */
        if (null !== $viewer->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL)) {
            return;
        }
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




        $this->params['viewerId'] = $viewer->getId();
        $this->params['accessDeny'] = PastoralAccessMapper::ACCESS_DENY;
        $this->params['viewerRoleId'] = $viewer->getRole()->getId();
        $this->params['viewerRoleType'] = $viewer->getRole()->getType();
        $this->filters[] = '(' . implode(' OR ', $filters) . ')';
    }

    /**
     * Initiates all the filters
     *
     * @return void
     */
    private function setFilters(): void
    {
        $this->getWhereDate();
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
            INNER JOIN pastoral_access pa ON pastoral.id = pa.pastoral_id AND pa.deleted_at IS NULL
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
        int $maxStack
    ): Collection {
        /* Return empty collection if either date is null.
         */
        if (null === $startDate || null === $endDate) {
            return new Collection();
        }

        $start = (clone $startDate)->startOfMonth();
        $end = (clone $endDate)->startOfMonth();

        $months = [];
        while ($end->gte($start) && count($months) < $maxStack) {
            array_unshift($months, strtolower($end->getUsingFormat($format)));
            $end->addMonths(-1);
        }
        return new Collection($months);
    }

    /**
     * @param Alaress_Date_Format|null $startDate
     * @param Alaress_Date_Format|null $endDate
     * @param string                   $format
     * @param int                      $maxStack
     * @return Collection
     */
    public function getWeeksInRange(
        ?Alaress_Date_Format $startDate,
        ?Alaress_Date_Format $endDate,
        string $format,
        int $maxStack
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
        $current = (clone $end);
        $index = 0;
        while ($current->gte($start) && count($weeks) < $maxStack) {
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
     * Build cursor conditions for lexicographic comparison
     */
    public function buildCursorConditions(array $cursor, int $minValue): array
    {
        $conditions = [];
        $params = [];

        $fieldMap = [
            Report::ORDER_OCCURRED_ON => [
                'condition' => 'COALESCE(pastoral.occurred_on, pastoral.created_at)',
                'param' => $cursor['created'],
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
                'condition' => 'c.name',
                'param' => $cursor['subtype']
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
                'condition' => "COALESCE(pastoral.points, {$minValue})",
                'param' => $cursor['points']
            ],
            Report::ORDER_YEAR => [
                'condition' => 'sy.sequence',
                'param' => $cursor['yearLevel']
            ],
            Report::ORDER_HOUSE => [
                'condition' => 's.house',
                'param' => $cursor['house']
            ],
            Report::ORDER_CAMPUS => [
                'condition' => 'sc.name',
                'param' => $cursor['campus']
            ],
            Report::ORDER_SEVERITY => [
                'condition' => 'sv.sequence',
                'param' => $cursor['severity']
            ],
            Report::ORDER_PASTORAL_ID => [
                'condition' => 'pastoral.id',
                'param' => $cursor['id']
            ],
        ];

        $sortFields = array_keys($fieldMap);

        // Build lexicographic comparison conditions
        for ($i = 0; $i < count($sortFields); $i++) {
            $condition = [];

            // Add equality conditions for all previous fields
            for ($j = 0; $j < $i; $j++) {
                $field = $sortFields[$j];
                $dbField = $fieldMap[$field]['condition'] ?? $field;
                $condition[] = "{$dbField} = :cursor_{$field}";
            }

            // Add comparison for current field
            $field = $sortFields[$i];
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
}
