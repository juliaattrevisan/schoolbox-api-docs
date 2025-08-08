<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Cursor;

class TypeByMonth extends Cursor implements TabTypeInterface
{
    private $params = [];
    private $months = [];
    private const COLUMN_QUERY_MONTH_FORMAT = '%b_%Y';
    private const COLUMN_DATE_FORMAT = "M_Y";

    private function getMonths(): Collection
    {
        if (empty($this->months)) {
            $this->months = $this->helper->getMonthsInRange(
                $this->helper->getStartDate(),
                $this->helper->getEndDate(),
                self::COLUMN_DATE_FORMAT,
            );
        }
        return $this->months;
    }
    private function getSelectCountString(string $resultType, string $label): string
    {
        $this->params[$label] = $label;
        return match (strtolower($resultType)) {
            Cursor::RESULT_TYPE_RECORDS =>
            "ROUND(COUNT(CASE WHEN monthData.month = '{$label}' THEN 1 END), 2) AS :{$label}",
            Cursor::RESULT_TYPE_POINTS =>
            "ROUND(SUM(CASE WHEN monthData.month = '{$label}' THEN monthData.points END), 2) AS :{$label}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
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
             type.name as title,
             subtype.name as subTitle,
             {$selectMonthsQuery}
          FROM lu_pastoral_type type
          LEFT JOIN lu_pastoral_subtype subtype ON subtype.pastoral_type_id = type.id
          LEFT JOIN (
                SELECT
                     p.type_id,
                     p.subtype_id,
                     p.points AS points,
                     DATE_FORMAT(COALESCE(p.occurred_on, p.created_at), '{$monthFormat}') AS month
                FROM (
                    SELECT DISTINCT p.*
                    {$this->helper->getFromString()}
                    WHERE {$whereQuery}
                ) p
            ) monthData
            ON monthData.type_id = type.id AND (monthData.subtype_id = subtype.id OR monthData.subtype_id IS NULL)
            WHERE {$whereInTypeQuery}
            GROUP BY title, subTitle
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
        if (isset($cursor)) {
            $sortingOperator = $this->order == Cursor::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            $whereDiscriminator = "
                WHERE (
                    title {$sortingOperator} :type OR
                    (title = :type AND subTitle {$sortingOperator} :subType)
            )";
            $params['type'] = $cursor['type'];
            $params['subType'] = $cursor['subType'];
        }
        $totalQuery = "
            SELECT withTotal.*
            FROM (
                {$this->getResultQuery()}
            ) withTotal
            {$whereDiscriminator}
            GROUP BY title, subTitle
            ORDER BY title {$this->order}, subTitle {$this->order}
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
            ]
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
            'type' => $last['title'],
            'subType' => $last['subTitle'],
        ];

        return $this->codec->encode($discriminator);
    }

    /**
     * @inheritdoc
     */
    public function process(Collection $records, ?string $encodedCursor = null)
    {
        $totalRow = array_merge(
            ['type_subtype' => 'Total'],
            $this->getTotalCount()
                         ->map(fn($count) => [ $this->resultType => is_numeric($count) ? $count / 1 : null ])->getAll()
        );
        return [
            'data' => [
                'tableHeaders' => $this->getTableHeaders(),
                'totalRow' => $totalRow,
                'rows' => $records->map(
                    function (array $record) {
                        $title    = array_shift($record);
                        $subTitle = array_shift($record);
                        $row['type_subtype'] = [
                            'title' => $title,
                            'subTitle' => $subTitle,
                        ];
                        return array_merge($row, array_map(fn($value) => [$this->resultType => $value], $record));
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
