<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Base;

class TypeByWeek extends Base implements TabTypeInterface, ICursor
{
    private $params = [];
    private $weeks = [];
    public const COLUMN_QUERY_KEY = 'week_%d';
    public const COLUMN_KEY_REGEX = '/(week)_(?<index>\d+)/';
    public const DEFAULT_SORTING_KEY = self::SORT_BY_TYPE_DEFAULT;

    private function getWeeks(): Collection
    {
        if (empty($this->weeks)) {
            $this->weeks = $this->helper->getWeeksInRange(
                $this->helper->getStartDate(),
                $this->helper->getEndDate(),
                self::COLUMN_QUERY_KEY,
            );
        }
        return $this->weeks;
    }

    private function getSelectCountString(string $resultType, string $label, \Alaress_Date_Format $start, \Alaress_Date_Format $end): string
    {
        $this->params[$label] = $label;
        $this->params["weekStart{$label}"] = $start->toIso8601String();
        $this->params["weekEnd{$label}"] = $end->toIso8601String();
        return match (strtolower($resultType)) {
            self::RESULT_TYPE_RECORDS =>
                "ROUND(COUNT(CASE WHEN reportDate BETWEEN :weekStart{$label} AND :weekEnd{$label} THEN 1 END), 2) AS :{$label}",
            self::RESULT_TYPE_POINTS =>
                "ROUND(SUM(CASE WHEN reportDate BETWEEN :weekStart{$label} AND :weekEnd{$label} THEN weekData.points END), 2) AS :{$label}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
    }

    private function getOrderBy(): string
    {
        if (strcasecmp($this->sortBy, 'total') === 0) {
            return "total {$this->order}";
        }
        if (in_array($this->sortBy, array_column($this->getWeeks()->getAll(), 'key'))) {
            return "{$this->sortBy} {$this->order}";
        }
        return "typeName {$this->order}, subtypeName {$this->order}";
    }

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
        $weeks = $this->getWeeks();
        $selectWeeksQuery =
            implode(
                ', ',
                $weeks->map(fn(array $week) => $this->getSelectCountString(
                    $this->resultType,
                    $week['key'],
                    $week['start'],
                    $week['end']
                ))->getAll()
            );
        $query = "
        SELECT
             type.id as typeId,
             subtype.id as subtypeId,
             type.name as typeName,
             subtype.name as subtypeName,
             CONCAT(
                type.name,
                CASE
                    WHEN subtype.name IS NOT NULL AND subtype.name <> ''
                        THEN CONCAT(' (', subtype.name, ')') ELSE '' END
             ) as type_subtype,
             {$selectWeeksQuery}
          FROM lu_pastoral_type type
          LEFT JOIN lu_pastoral_subtype subtype ON subtype.pastoral_type_id = type.id
          LEFT JOIN (
                SELECT
                     pastoral.type_id,
                     pastoral.subtype_id,
                     pastoral.points AS points,
                     COALESCE(pastoral.occurred_on, pastoral.created_at) AS reportDate
                FROM (
                    SELECT DISTINCT pastoral.*
                    {$this->helper->getFromString()}
                    WHERE {$whereQuery}
                ) pastoral
            ) weekData
            ON weekData.type_id = type.id AND (weekData.subtype_id = subtype.id OR weekData.subtype_id IS NULL)
            WHERE {$whereInTypeQuery}
            GROUP BY typeId, subtypeId
        ";

        $totalCount = "CASE WHEN ";
        $totalCount .= implode(" AND ", $weeks->map(fn(array $week) => "{$week['key']} IS NULL")->getAll());
        $totalCount .= " THEN NULL ELSE ";
        $totalCount .= "(ROUND(" . implode(' + ', $weeks->map(fn(array $week) => "COALESCE({$week['key']}, 0)")->getAll()) . ", 2)) END as total";
        return "
            SELECT
                t.*,
                {$totalCount}
            FROM ($query) t
        ";
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        $params = [];
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
        }
        $db = $this->pastoralMapper->getDatabase();
        $whereDiscriminator = '';
        $orderBy = $this->getOrderBy();
        if (isset($cursor)) {
            $minValue = self::MIN_SORT_BY_VALUE;
            $sortingOperator = $this->order == self::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            if (array_key_exists('count', $cursor)) {
                if (
                    in_array($this->sortBy, array_column($this->getWeeks()->getAll(), 'key')) ||
                    strcasecmp($this->sortBy, 'total') === 0
                ) {
                    $whereDiscriminator = "
                    WHERE (
                      (COALESCE($this->sortBy, {$minValue}) {$sortingOperator} COALESCE(:countDiscriminator, {$minValue})) OR
                        (COALESCE($this->sortBy, {$minValue}) = COALESCE(:countDiscriminator, {$minValue}) AND typeId {$sortingOperator} :typeId) OR
                        (COALESCE($this->sortBy, {$minValue}) = COALESCE(:countDiscriminator, {$minValue}) AND typeId = :typeId AND COALESCE(subtypeId, {$minValue}) {$sortingOperator} COALESCE(:subtypeId, {$minValue}))
                    )";
                }
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
            {$whereDiscriminator}
            GROUP BY typeId, subtypeId
            ORDER BY {$orderBy}, typeId {$this->order}, subtypeId {$this->order}
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

    public function retrieveResultCount(): ?int
    {
        return $this->helper->getType()->count() + $this->helper->getSubTypes()->count();
    }

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
        foreach ($this->getWeeks() as $week) {
            $tableHeaders[] = [
                'key' => $week['key'],
                'title' => $week['label'],
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ];
        }
        return $tableHeaders;
    }

    protected function getTotalCount(): Collection
    {
        $db = $this->pastoralMapper->getDatabase();
        $selectSUM = implode(',', $this->getWeeks()->map(fn(array $week) => "SUM({$week['key']}) as {$week['key']}")->getAll());
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

    public function current(Collection $collection): ?string
    {
        return null;
    }

    public function next(Collection $collection): ?string
    {
        if ($collection->count() === 0) {
            return null;
        }
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

        if (
            in_array($this->sortBy, array_column($this->getWeeks()->getAll(), 'key')) ||
            strcasecmp('total', $this->sortBy) === 0
        ) {
            $discriminator['count'] = $last[$this->sortBy];
        }

        return $this->codec->encode($discriminator);
    }

    /**
     * @inheritdoc
     */
    public function process(Collection $records, ?string $encodedCursor = null): array
    {
        $totalCount = $this->getTotalCount();
        $totalCount = $totalCount->map(fn($count) => is_numeric($count) ? $count / 1 : null);

        $totalRow = array_merge(
            ['type_subtype' => 'Total'],
            $totalCount->map(fn($count) => [ $this->resultType => $count ])->getAll()
        );
        unset($totalCount['total']);
        $weeks = array_column($this->getWeeks()->getAll(), 'label', 'key');

        $totalRow['trend'] = [
            'line' => array_values($totalCount->getAll()),
            'tooltips' => array_map(
                function (string $week) use ($weeks) {
                    return $weeks[$week];
                },
                $totalCount->keys()
            )
        ];

        return [
            'data' => [
                'tableHeaders' => $this->getTableHeaders(),
                'totalRow' => $totalRow,
                'rows' => $records->map(
                    function (array $record) use ($weeks) {
                        $row['type_subtype'] = [
                            'type' => $record['typeName'],
                            'subtype' => $record['subtypeName'],
                            'typeId' => $record['typeId'],
                            'subtypeId' => $record['subtypeId'],
                        ];
                        unset($record['typeName'], $record['subtypeName'], $record['typeId'], $record['subtypeId'], $record['type_subtype']);
                        $row = array_merge($row, array_map(fn($value) => [$this->resultType => $value], $record));
                        unset($record['total']);
                        $row['trend'] = [
                            'line' => array_values($record),
                            'tooltips' => array_map(
                                fn (string $week) => $weeks[$week],
                                array_keys($record)
                            )
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

    /**
     * Get the CSV headers for the export
     *
     * @return array
     */
    public function getCSVHeaders(): array
    {
        $headers = [
            'type_subtype' => 'Type/Sub-Type',
            'total' => 'Total',
        ];
        foreach ($this->getWeeks() as $week) {
            $headers[$week['key']] = $week['label'];
        }
        return $headers;
    }
}
