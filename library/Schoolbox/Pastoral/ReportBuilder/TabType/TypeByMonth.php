<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Base;

class TypeByMonth extends Base implements TabTypeInterface, ICursor
{
    private $params = [];
    private $months = [];
    public const COLUMN_QUERY_MONTH_FORMAT = '%b_%Y';
    public const COLUMN_DATE_FORMAT = "M_Y";
    public const COLUMN_KEY_REGEX = "/(?<month>(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec))_(?<year>[0-9]{4})/i";
    public const MAX_COLUMN_COUNT = 12; // Maximum number of months to display
    private function getMonths(): Collection
    {
        if (empty($this->months)) {
            $this->months = $this->helper->getMonthsInRange(
                $this->helper->getStartDate(),
                $this->helper->getEndDate(),
                self::COLUMN_DATE_FORMAT,
                self::MAX_COLUMN_COUNT,
            );
        }
        return $this->months;
    }
    private function getSelectCountString(string $resultType, string $label): string
    {
        $this->params[$label] = $label;
        return match (strtolower($resultType)) {
            self::RESULT_TYPE_RECORDS =>
            "ROUND(COUNT(CASE WHEN monthData.month = '{$label}' THEN 1 END), 2) AS :{$label}",
            self::RESULT_TYPE_POINTS =>
            "ROUND(SUM(CASE WHEN monthData.month = '{$label}' THEN monthData.points END), 2) AS :{$label}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
    }

    private function getOrderBy(): string
    {
        if (strcasecmp($this->sortBy, 'total') === 0) {
            return "total {$this->order}";
        }

        if ($this->getMonths()->contains($this->sortBy)) {
            return "{$this->sortBy} {$this->order}";
        }

        return "typeName {$this->order}, subtypeName {$this->order}";
    }

    /**
     * @return string
     */
    private function getResultQuery(): string
    {
        $inTypeString = $inSubTypeString = '';
        if ($this->helper->getType()->count() > 0) {
            $inTypeString = "type.id IN (" . implode(',', $this->helper->getType()->getPropertyValues('id')) . ")";
        }

        if ($this->helper->getSubTypes()->count() > 0) {
            $inSubTypeString = "subtype.id IN (" . implode(',', $this->helper->getSubTypes()->getPropertyValues('id')) . ")";
        }

        $whereInTypeQuery = implode(' OR ', array_filter([$inTypeString, $inSubTypeString]));
        $whereQuery       = "(" . implode(" AND ", $this->helper->getFilters()) . ")";

        $months = $this->getMonths();

        $selectMonthsQuery =
            implode(
                ', ',
                $months->map(fn(string $month) => $this->getSelectCountString($this->resultType, $month))->getAll()
            );

        $monthFormat = self::COLUMN_QUERY_MONTH_FORMAT;

        $query = "
        SELECT
             type.id as typeId,
             subtype.id as subtypeId,
             type.name as typeName,
             subtype.name as subtypeName,
             {$selectMonthsQuery}
          FROM lu_pastoral_type type
          LEFT JOIN lu_pastoral_subtype subtype ON subtype.pastoral_type_id = type.id
          LEFT JOIN (
                SELECT
                     pastoral.type_id,
                     pastoral.subtype_id,
                     pastoral.points AS points,
                     DATE_FORMAT(COALESCE(pastoral.occurred_on, pastoral.created_at), '{$monthFormat}') AS month
                FROM (
                    SELECT DISTINCT pastoral.*
                    {$this->helper->getFromString()}
                    WHERE {$whereQuery}
                ) pastoral
            ) monthData
            ON monthData.type_id = type.id AND (monthData.subtype_id = subtype.id OR monthData.subtype_id IS NULL)
            WHERE {$whereInTypeQuery}
            GROUP BY typeId, subtypeId
        ";

        $totalCount = "CASE WHEN ";
        $totalCount .= implode(" AND ", $months->map(fn(string $month) => "{$month} IS NULL")->getAll());
        $totalCount .= " THEN NULL ELSE ";
        $totalCount .= "(ROUND(" . implode(' + ', $months->map(fn(string $month) => "COALESCE({$month}, 0)")->getAll()) . ", 2)) END as total";

        return "
            SELECT
                t.*,
                {$totalCount}
            FROM ($query) t
        ";
    }

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $params = ['limit' => $this->limit];
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
        }

        $db = $this->pastoralMapper->getDatabase();

        $whereDiscriminator = '';
        $orderBy = $this->getOrderBy();
        if (isset($cursor)) {
            $minValue = self::MIN_SORT_BY_VALUE;
            $sortingOperator = $this->order == self::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            if (
                array_key_exists('count', $cursor) &&
                ($this->getMonths()->contains($this->sortBy) || strcasecmp($this->sortBy, 'total') === 0)
            ) {
                $whereDiscriminator = "
                WHERE (
                  (COALESCE($this->sortBy, {$minValue}) {$sortingOperator} COALESCE(:countDiscriminator, {$minValue})) OR
                    (COALESCE($this->sortBy, {$minValue}) = COALESCE(:countDiscriminator, {$minValue}) AND typeId {$sortingOperator} :typeId) OR
                    (COALESCE($this->sortBy, {$minValue}) = COALESCE(:countDiscriminator, {$minValue}) AND typeId = :typeId AND COALESCE(subtypeId, {$minValue}) {$sortingOperator} COALESCE(:subtypeId, {$minValue}))
                )";
                $params['countDiscriminator'] = $cursor['count'];
            } else {
                $whereDiscriminator  = "
                WHERE (
                    typeName {$sortingOperator} :type OR
                    (typeName = :type AND COALESCE(subtypeName, '') {$sortingOperator} COALESCE(:subType, '')) OR
                    (typeName = :type AND COALESCE(subtypeName, '') = COALESCE(:subType, '') AND typeId {$sortingOperator} :typeId) OR
                    (typeName = :type AND COALESCE(subtypeName, '') = COALESCE(:subType, '') AND typeId = :typeId AND COALESCE(subtypeId, {$minValue})  {$sortingOperator} COALESCE(:subtypeId, {$minValue}))
            )";
                $params['type']      = $cursor['type'];
                $params['subType']   = $cursor['subType'];
            }
            $params['typeId']    = $cursor['typeId'];
            $params['subtypeId'] = $cursor['subtypeId'];
        }
        $totalQuery = "
            SELECT withTotal.*
            FROM (
                {$this->getResultQuery()}
            ) withTotal
            {$whereDiscriminator}
            GROUP BY typeId, subtypeId
            ORDER BY {$orderBy}, typeId {$this->order}, subtypeId {$this->order}
            LIMIT :limit
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
        /* The count is always the total number of types and subtypes selected in the report.
         */
        return $this->helper->getType()->count() + $this->helper->getSubTypes()->count();
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
                'key' => 'type_subtype',
                'title' => 'Type/Sub-Type',
                'type' => 'TypeSubtype',
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

    private function getTotalCount(): Collection
    {
        $db = $this->pastoralMapper->getDatabase();

        $selectSUM = implode(',', $this->getMonths()->map(fn(string $month) => "SUM({$month}) as {$month}")->getAll());
        $query = "
         SELECT SUM(total) as total, {$selectSUM}
         FROM ({$this->getResultQuery()}) as count
        ";

        $result =  $db->executeQuery(
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
     * @inheritdoc
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

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

        $discriminator = [
            'type' => $last['typeName'],
            'subType' => $last['subtypeName'],
            'typeId' => $last['typeId'],
            'subtypeId' => $last['subtypeId'],
        ];

        if ($this->getMonths()->contains($this->sortBy) || strcasecmp('total', $this->sortBy) === 0) {
            $discriminator['count'] = $last[$this->sortBy];
        }

        return $this->codec->encode($discriminator);
    }

    /**
     * @inheritdoc
     */
    public function process(Collection $records, ?string $encodedCursor = null)
    {
        $totalCount = $this->getTotalCount();
        $totalCount = $totalCount->map(fn($count) => is_numeric($count) ? $count / 1 : null);

        $totalRow = array_merge(
            ['type_subtype' => 'Total'],
            $totalCount->map(fn($count) => [ $this->resultType => $count ])->getAll()
        );
        unset($totalCount['total']);
        $totalRow['trend'] = [
            'line' => array_values($totalCount->getAll()),
            'tooltips' => array_map(fn(string $key) => ucwords(str_replace('_', ' ', $key)), $totalCount->keys()),
        ];
        return [
            'data' => [
                'tableHeaders' => $this->getTableHeaders(),
                'totalRow' => $totalRow,
                'rows' => $records->map(
                    function (array $record) {
                        $row['type_subtype'] = [
                            'type' => $record['typeName'],
                            'subtype' => $record['subtypeName'],
                            'typeId' => $record['typeId'],
                            'subtypeId' => $record['subtypeId'],

                        ];
                        unset($record['typeName'], $record['subtypeName'], $record['typeId'], $record['subtypeId']);
                        $row = array_merge($row, array_map(fn($value) => [$this->resultType => $value], $record));
                        unset($record['total']);
                        $row['trend'] = [
                            'line' => array_values($record),
                            'tooltips' => array_map(fn(string $key) => ucwords(str_replace('_', ' ', $key)), array_keys($record)),
                        ];
                        return $row;
                    }
                )->getAll()
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
