<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Base;

class StudentByMonth extends Base implements TabTypeInterface, ICursor
{
    private array $params = [];
    private ?Collection $months = null;
    public const COLUMN_QUERY_MONTH_FORMAT = '%b_%Y';
    public const COLUMN_DATE_FORMAT = "M_Y";
    public const COLUMN_KEY_REGEX = "/(?<month>(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec))_(?<year>[0-9]{4})/i";
    public const DEFAULT_SORTING_KEY = self::SORT_BY_STUDENT_DEFAULT;

    /**
     * Get the months in the date range
     *
     * @return Collection
     */
    private function getMonths(): Collection
    {
        if (null === $this->months) {
            $this->months = $this->helper->getMonthsInRange(
                $this->helper->getStartDate(),
                $this->helper->getEndDate(),
                self::COLUMN_DATE_FORMAT,
            );
        }
        return $this->months;
    }

    /**
     * Get the select string for the counts by month
     *
     * @param string $resultType
     * @param string $month
     * @return string
     * @throws \Exception
     */
    private function getSelectCountString(string $resultType, string $month): string
    {
        $this->params[$month] = $month;

        return match (strtolower($resultType)) {
            self::RESULT_TYPE_RECORDS =>
            "ROUND(COUNT(CASE WHEN distinctRecords.month = '{$month}' THEN 1 END), 2) AS :{$month}",
            self::RESULT_TYPE_POINTS =>
            "ROUND(SUM(CASE WHEN distinctRecords.month = '{$month}' THEN distinctRecords.points END), 2) AS :{$month}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
    }

    /**
     * Validate and return the relevant order query to sort the results
     *
     * @return string
     */
    private function getOrderBy(): string
    {
        if (strcasecmp($this->sortBy, 'total') === 0) {
            return "total {$this->order}";
        }

        if ($this->getMonths()->contains($this->sortBy)) {
            return "{$this->sortBy} {$this->order}";
        }

        /* Otherwise default to sorting by name
         */
        return "u.lastname {$this->order}, COALESCE(u.preferred_name, u.firstname) {$this->order}";
    }

    /**
     * Get the total count for all months
     *
     * @return Collection
     */
    protected function getTotalCount(): Collection
    {
        $db = $this->pastoralMapper->getDatabase();

        $selectSUM = implode(',', $this->getMonths()->map(fn(string $month) => "SUM({$month}) as {$month}")->getAll());

        $query = "
         SELECT SUM(total) as total, {$selectSUM}
         FROM ({$this->getResultQuery()}) as count
        ";

        $result = $db->executeQuery(
            $query,
            array_merge(
                $this->helper->getParams(),
                $this->params
            )
        );

        $data = $result->fetch(\PDO::FETCH_ASSOC);
        return new Collection($data);
    }

    /**
     * Build the main result query
     *
     * @return string
     */
    private function getResultQuery(): string
    {
        $whereQuery = implode(" AND ", $this->helper->getFilters());

        $months = $this->getMonths();
        $countQuery = implode(
            ', ',
            $months->map(fn(string $month) => $this->getSelectCountString($this->resultType, $month))->getAll()
        );

        $monthFormat = self::COLUMN_QUERY_MONTH_FORMAT;

        $distinctRecordsQuery = "
            SELECT DISTINCT pastoral.*, CONCAT(s.lastname, ', ', s.chosen_name) as `user`, DATE_FORMAT(COALESCE(pastoral.occurred_on, pastoral.created_at), '{$monthFormat}') AS month
            {$this->helper->getFromString()}
            WHERE
                {$whereQuery}
        ";

        $countQuery = "
            SELECT distinctRecords.student_id, distinctRecords.`user`, {$countQuery}
            FROM ({$distinctRecordsQuery}) distinctRecords
            GROUP BY distinctRecords.student_id
        ";

        // Calculate total for each student
        $totalCount = "CASE WHEN ";
        $totalCount .= implode(" AND ", $months->map(fn(string $month) => "{$month} IS NULL")->getAll());
        $totalCount .= " THEN NULL ELSE ";
        $totalCount .= "(ROUND(" . implode(' + ', $months->map(fn(string $month) => "COALESCE({$month}, 0)")->getAll()) . ", 2)) END as total";

        $query = "SELECT countData.*, {$totalCount}
            FROM ($countQuery) countData
        ";

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $params = [];
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
        }

        $db = $this->pastoralMapper->getDatabase();

        /**
         * Finding out where to locate the position where the cursor is pointing
         */
        $whereParts = "";
        $groupBy = "u.lastname, COALESCE(u.preferred_name, u.firstname)";

        $orderBy = $this->getOrderBy();

        if (isset($cursor)) {
            $minCount = self::MIN_SORT_BY_VALUE;
            $sortingOperator = $this->order == self::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            if (
                array_key_exists('count', $cursor) &&
                ($this->getMonths()->contains($this->sortBy) || strcasecmp('total', $this->sortBy) === 0)
            ) {
                $whereParts = "
                WHERE (
                          (withTotal.student_id {$sortingOperator} :studentId AND COALESCE({$this->sortBy}, {$minCount}) = COALESCE(:countDiscriminator, {$minCount})) OR
                          (COALESCE({$this->sortBy}, {$minCount}) {$sortingOperator} COALESCE(:countDiscriminator, {$minCount}))
                      )
                ";
                $params['countDiscriminator'] = $cursor['count'];
            } else {
                $whereParts = "
                CROSS JOIN (SELECT id, lastname, COALESCE(preferred_name, firstname) as preferred_name
                FROM user
                WHERE id = :studentId) u2
                WHERE (
                    u.lastname {$sortingOperator} u2.lastname OR
                    (u.lastname = u2.lastname AND COALESCE(u.preferred_name, u.firstname) {$sortingOperator} u2.preferred_name) OR
                    (u.lastname = u2.lastname AND COALESCE(u.preferred_name, u.firstname) = u2.preferred_name AND u.id {$sortingOperator} :studentId)
                )
            ";
            }
            $params['studentId'] = $cursor['id'];
        }

        $limit = "";
        if (null !== $this->limit) {
            $limit = " LIMIT :limit ";
            $params['limit'] = $this->limit;
        }

        $totalQuery = "
            SELECT withTotal.*
            FROM (
                    {$this->getResultQuery()}
                ) withTotal
            INNER JOIN user u ON withTotal.student_id = u.id
            {$whereParts}
            GROUP BY withTotal.student_id, {$groupBy}
            ORDER BY {$orderBy}, withTotal.student_id {$this->order}
            {$limit}
        ";

        $result = $db->executeQuery(
            $totalQuery,
            array_merge(
                $this->params,
                $params,
                $this->helper->getParams()
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
        $whereFilterQuery = implode(" AND ", $this->helper->getFilters());

        $query = "
            SELECT pastoral.student_id
            {$this->helper->getFromString()}
            WHERE
                {$whereFilterQuery}
            GROUP BY pastoral.student_id
        ;";

        $result = $this->pastoralMapper->getDatabase()->executeQuery(
            $query,
            $this->helper->getParams()
        );

        return $result->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * Get the next cursor for pagination
     *
     * @param Collection $collection
     * @return string|null
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        /* If result size is smaller than limit, assumed that it is at the end of pointer and terminate early
         */
        if ($collection->count() < $this->limit) {
            return null;
        }

        $last = $collection->last();

        $discriminator = ['id' => $last['student_id']];

        if ($this->getMonths()->contains($this->sortBy) || strcasecmp('total', $this->sortBy) === 0) {
            $discriminator['count'] = $last[$this->sortBy];
        }

        return $this->codec->encode($discriminator);
    }

    /**
     * Get the header row for the table
     *
     * @return array[]
     */
    private function getTableHeaders(): array
    {
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
            ],
            [
                'key' => 'trend',
                'title' => 'Trend',
                'type' => 'Trend',
                'isSortable' => false,
            ],
        ];

        foreach ($this->getMonths() as $month) {
            $tableHeaders[] = [
                'key' => $month,
                'title' => ucwords(str_replace('_', ' ', $month)),
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ];
        }

        return $tableHeaders;
    }

    /**
     * Processing the payload to format it for presenting in a table
     *
     * @param Collection  $records
     * @param string|null $encodedCursor
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
            $totalCount = $this->getTotalCount();
            $totalCount = $totalCount->map(fn($count) => is_numeric($count) ? $count / 1 : null);

            $totalRow = array_merge(
                ['user' => 'Total'],
                $totalCount->map(fn($count) => [$this->resultType => $count])->getAll()
            );

            // Create trend data for total row
            $trendData = $totalCount->getAll();
            unset($trendData['total']);
            $totalRow['trend'] = [
                'line' => array_values($trendData),
                'tooltips' => array_map(fn(string $key) => ucwords(str_replace('_', ' ', $key)), array_keys($trendData)),
            ];

            $rows = $records->map(
                function (array $record) use ($students) {
                    $row['user'] = call_user_func($this->userTranslator, $students->getBy('id', $record['student_id']));
                    unset($record['student_id'], $record['user']);

                    $row = array_merge($row, array_map(fn($value) => [$this->resultType => $value], $record));

                    // Create trend data for this student
                    $trendData = $record;
                    unset($trendData['total']);
                    $row['trend'] = [
                        'line' => array_values($trendData),
                        'tooltips' => array_map(fn(string $key) => ucwords(str_replace('_', ' ', $key)), array_keys($trendData)),
                    ];

                    return $row;
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

    /**
     * Get the CSV headers for the export
     *
     * @return array
     */
    public function getCSVHeaders(): array
    {
        $headers = [
            'user' => 'Name',
            'total' => 'Total',
        ];
        foreach ($this->getMonths() as $month) {
            $headers[$month] = ucwords(str_replace('_', ' ', $month));
        }
        return $headers;
    }
}
