<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Alaress\Codec\ICodec;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Alaress_Date_Format;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;

class StudentByType extends Cursor implements TabTypeInterface
{
    public function __construct(
        array $report,
        ModelToArray $userTranslator,
        UserMapper $userMapper,
        TermMapper $termMapper,
        PastoralMapper $pastoralMapper,
        string $resultType,
        ?int $limit = self::LIMIT_DEFAULT,
        ?ICodec $codec = null
    ) {
        parent::__construct($report, $userTranslator, $userMapper, $termMapper, $pastoralMapper, $resultType, $limit, $codec);
        $this->getFilters();
    }

    /**
     * This will return the type that doesn't have subtypes
     *
     * @return Collection
     */
    private function getType(): Collection
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

    private function getSeverity(): void
    {
        $configLoader = Loader::getInstance();
        if ($this->report['severity'] && $configLoader->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
            $this->filters[] = "`sv`.`sequence` >= :severity";
            $this->params['severity'] = $this->report['severity']->getSequence();
        }
    }

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
            $this->filters[] = $query;
        }
    }

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

    private function getCampuses(): void
    {
        if ($this->report['campuses']->count() > 0) {
            $this->filters[] = '`c`.`campus` IN (' .
                implode(',', $this->report['campuses']->getPropertyValues('id')) .
                ')';
        }
    }

    private function getAuthors(): void
    {
        if ($this->report['authors']->count() > 0) {
            $this->filters[] = "p.author_id IN (" .
                implode(',', $this->report['authors']->getPropertyValues('id')) .
                ")";
        }
    }

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

    private function getExpired(): void
    {
        if (!$this->report['includeArchivedRecords']) {
            $this->filters[] = "(`p`.`expires_at` >= CURDATE() OR `p`.`expires_at` IS NULL)";
        }
    }

    private function getTags(): void
    {
        if ($this->report['tags']->count() > 0) {
            $this->filters[] = '`t`.`tag_id` IN (' .
                implode(',', $this->report['tags']->getPropertyValues('id')) .
                ')';
        }
    }

    /**
     * Get all the filters from the report setting
     *
     * @return void
     */
    private function getFilters(): void
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

    private function shouldFilterAction(): bool
    {
        $configLoader = Loader::getInstance();
        return $configLoader->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED) &&
            (
                ($this->report['actionStatus'] ?? false) ||
                $this->report['includeRecordsWithoutAction'] ||
                $this->report['actions']->count() > 0
            );
    }

    private function getFromString(): string
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
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
        }

        $db = $this->pastoralMapper->getDatabase();

        $whereInTypeSubTypeQuery = [];

        $whereTypes = $this->getType();

        if ($whereTypes->count() > 0) {
            $whereTypeIds = implode(',', Collection::arrayColumn($whereTypes, 'id'));
            $countTypeQuery = implode(
                ', ',
                $whereTypes->map(
                    fn(Type $type) => "COUNT(CASE WHEN p.type_id = {$type->getId()} THEN 1 END) AS count_type_{$type->getId()}"
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
        }

        $whereSubTypes = $this->report['subtypes'] ?? [];
        if (!empty($whereSubTypes)) {
            $whereSubTypeIds   = implode(',', Collection::arrayColumn($this->report['subtypes'], 'id'));
            $countSubTypeQuery = implode(
                ', ',
                $this->report['subtypes']->map(
                    fn(SubType $subType) => "COUNT(CASE WHEN p.subtype_id = {$subType->getId()} THEN 1 END) AS count_subtype_{$subType->getId()}"
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
        }

        $countQuery = implode(', ', array_filter([$countTypeQuery ?? '', $countSubTypeQuery ?? '']));

        $whereTypeSubTypeQuery = implode(" OR ", $whereInTypeSubTypeQuery);

        $orderBy = "u.lastname, COALESCE(u.preferred_name, u.firstname)";

        $whereQuery = implode(" AND ", $this->filters);

        $countQuery =
            "SELECT p.student_id, {$countQuery}
            {$this->getFromString()}
            WHERE
                ({$whereTypeSubTypeQuery}) AND
                {$whereQuery} AND
                p.deleted_at IS NULL
            GROUP BY p.student_id
           ";

        $typeKeys = implode(
            ' + ',
            $whereTypes->map(
                fn(Type $type) => "count_type_{$type->getId()}"
            )->getAll()
        );

        $subTypeKeys = implode(
            ' + ',
            $this->report['subtypes']->map(
                fn(SubType $subType) => "count_subtype_{$subType->getId()}"
            )->getAll()
        );

        $totalCount = "SUM(" . implode(' + ', array_filter([$typeKeys, $subTypeKeys])) . ") as total";

        /**
         * Finding out where to locate the position where the cursor is pointing
         */
        $whereParts = "";
        if (isset($cursor)) {
            /* TODO default to ordering by name in ascending order, will update in SA-20574 when implementing sorting
             */
            $whereParts = "
                CROSS JOIN (SELECT lastname, COALESCE(preferred_name, firstname) as preferred_name
                FROM user
                WHERE id = {$cursor['id']}) u2
                WHERE u.lastname > u2.lastname OR (u.lastname > u2.lastname AND COALESCE(u.preferred_name, u.firstname) > u2.preferred_name)
            ";
        }

        $totalQuery = "
            SELECT countData.*, {$totalCount}
            FROM ($countQuery) countData
            INNER JOIN user u ON countData.student_id = u.id
            {$whereParts}
            GROUP BY countData.student_id, {$orderBy}
            ORDER BY {$orderBy} ASC
            LIMIT :limit
        ";

        $result =  $db->executeQuery(
            $totalQuery,
            array_merge(
                [
                    'limit' => $this->limit,
                ],
                $this->params
            )
        );

        $data = $result->fetchAll(\PDO::FETCH_ASSOC);
        return new Collection($data);
    }

    /**
     * @inheritdoc
     */
    public function retrieveResultCount(): ?int
    {
        $whereInTypeSubTypeQuery = [];
        if ($this->getType()->count() > 0) {
            $whereTypeIds = implode(',', Collection::arrayColumn($this->getType(), 'id'));
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
        }
        if ($this->report['subtypes']->count() > 0) {
            $whereSubTypeIds = implode(',', Collection::arrayColumn($this->report['subtypes'], 'id'));
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
        }

        $whereQuery = implode(" OR ", array_filter($whereInTypeSubTypeQuery));
        $whereFilterQuery = implode(" AND ", $this->filters);

        $query = "
            SELECT p.student_id
            {$this->getFromString()}
            WHERE
                {$whereQuery}
                AND {$whereFilterQuery}
                AND p.deleted_at IS NULL
            GROUP BY p.student_id
        ;";

        $result = $this->pastoralMapper->getDatabase()->executeQuery(
            $query,
            $this->params
        );

        return $result->rowCount();
    }

    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        $last = $collection->last();
        return $this->codec->encode([
            'id' => $last['student_id'],
            'orderBy' => 'name' //TODO SA-20574 for sorting in different types
        ]);
    }

    /**
     * Get the header row for the table
     *
     * @return array[]
     */
    private function getTableHeaders(): array
    {
        $types = $this->getType();

        $tableHeaders = [
            [
                'key' => 'user',
                'title' => 'Name',
                'type' => 'User',
                'isSortable' => true,
            ],
            [
                'key' => 'total',
                'title' => 'Total',
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ]
        ];

        foreach ($types as $type) {
            $tableHeaders[] = [
                'key' => "count_type_{$type->getId()}",
                'title' => $type->getName(),
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ];
        }

        foreach ($this->report['subtypes'] as $subType) {
            if ($subType instanceof SubType) {
                $tableHeaders[] = [
                    'key'   => "count_subtype_{$subType->getId()}",
                    'title' => $subType->getPastoralType()->getName(),
                    'subTitle' => $subType->getName(),
                    'type' => 'RecordsAndPoints',
                    'isSortable' => true,
                ];
            }
        }

        return $tableHeaders;
    }

    /**
     * Processing the payload to format it for presenting in a table
     *
     * @param Collection $records
     * @return array[]
     */
    public function process(Collection $records, ?string $encodedCursor = null): array
    {
        $tableHeader = $this->getTableHeaders();
        $totalRow = $rows = [];
        $students = $this->userMapper->findByColumn('id', array_column($records->getAll(), 'student_id'));

        /* Only process if there are records in the paginated results
         */
        if ($records->count() > 0) {
            $keys = array_keys($records->first());
            array_shift($keys);

            $totalRow['user'] = 'Total';

            foreach ($keys as $key) {
                $totalRow[$key] = ['records' => array_sum(array_column($records->getAll(), $key))];
            }

            $rows = $records->map(
                function (array $record) use ($students) {
                    $row['user'] = call_user_func($this->userTranslator, $students->getBy('id', $record['student_id']));
                    unset($record['student_id']);
                    return array_merge($row, array_map(fn($value) => ['records' => (int)$value], ($record)));
                }
            );
        }

        return [
            'data' => [
                'tableHeaders' => $tableHeader,
                'totalRow' => $totalRow,
                'rows' => $rows,
            ],
            'metadata' => [
                'count' => $this->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $this->current($records),
                    'next' => $this->next($records) ?? null,
                ]
            ]
        ];
    }
}
