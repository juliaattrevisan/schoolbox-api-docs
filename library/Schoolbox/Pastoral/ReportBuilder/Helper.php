<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\SubType\Model as SubType;

class Helper
{
    private array $filters = ['p.deleted_at IS NULL'];
    private array $params = [];

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
            $this->filters[] = "(COALESCE(`p`.`occurred_on`, `p`.`created_at`) <= :beforeDate)";
            $this->params['beforeDate'] = $this->report['beforeDate']->endOfDay()->toIso8601String();
        }

        if ($this->report['afterDate'] instanceof Alaress_Date_Format) {
            $this->filters[] = "(COALESCE(`p`.`occurred_on`, `p`.`created_at`) >= :afterDate)";
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
            $this->filters[] = "p.student_id IN (" .
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
            $filters[] = "`u`.`year_id` IS NULL";
        }

        if ($this->report['years']->count() > 0) {
            $filters[] = '`u`.`year_id` IN (' .
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
            $filters[] = "`u`.`house` IS NULL";
        }

        if ($this->report['houses']->count() > 0) {
            $filters[] = '`u`.`house` IN (' .
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
            $this->filters[] = '`c`.`campus` IN (' .
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
            $this->filters[] = "p.author_id IN (" .
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
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `role_id` = :user_role_id_0 AND `pastoral_id` = `p`.`id`) > 0 OR
                    (SELECT COUNT(*) FROM `pastoral_access` WHERE `user_id` = :user_id_0      AND `pastoral_id` = `p`.`id`) > 0 OR
                    (
                        SELECT COUNT(*) FROM `pastoral_access` WHERE `group_id` IN (
                            SELECT `group_id`
                            FROM `pastoral_group_member`
                            WHERE `user_id` = :user_id_1
                        ) AND `pastoral_id` = `p`.`id`
                    ) > 0 OR
                    `p`.`owner_id` = :user_id_2
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
            $this->filters[] = "(`p`.`expires_at` >= CURDATE() OR `p`.`expires_at` IS NULL)";
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
    }

    /**
     * Get the FROM string of the query
     *
     * @return string
     */
    public function getFromString(): string
    {

        $fromString = "
            FROM {$this->pastoralMapper->getTable()} p
            INNER JOIN user u ON p.student_id = u.id AND u.deleted_at IS NULL
            LEFT JOIN lu_pastoral_severity sv ON p.severity_id = sv.id
            LEFT JOIN userCampus c ON u.id = c.uid
        ";

        if ($this->shouldFilterAction()) {
            $joinType = $this->report['includeRecordsWithoutAction'] ? 'LEFT' : 'INNER';
            $fromString .= "
                {$joinType} JOIN pastoral_record_action a ON p.id = a.pastoral_id AND a.deleted_at IS NULL
            ";
        }

        if ($this->report['tags']->count() > 0) {
            $fromString .= "
                INNER JOIN pastoral_tags t on p.id = t.pastoral_id
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
        $current = (clone $endDate)->startOfMonth();

        $months = [];
        while ($current->gte($start)) {
            if ((Alaress_Date_Format::now())->endOfMonth() >= (clone $current)->endOfMonth()) {
                array_unshift($months, strtolower($current->getUsingFormat($format)));
            }
            $current->addMonths(-1);
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
        if ((clone $end)->endOfDay()->gt((Alaress_Date_Format::now())->endOfDay())) {
            $current = (Alaress_Date_Format::now());
        } else {
            $current = (clone $end);
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
}
