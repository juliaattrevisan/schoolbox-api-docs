<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;

class StudentByType extends Cursor implements TabTypeInterface
{
    private array $params = [];

    /**
     * Get the select string for the counts
     *
     * @param string $resultType
     * @param int    $value
     * @param bool   $isSubtype
     * @return string
     * @throws \Exception
     */
    private function getSelectCountString(string $resultType, int $value, bool $isSubtype = false): string
    {
        $type = $isSubtype ? 'subtype' : 'type';

        $label = "count_{$type}_{$value}";
        $this->params[$label] = $label;

        return match (strtolower($resultType)) {
            Cursor::RESULT_TYPE_RECORDS =>
            "ROUND(COUNT(CASE WHEN p.{$type}_id = {$value} THEN 1 END), 2) AS :{$label}",
            Cursor::RESULT_TYPE_POINTS =>
            "ROUND(SUM(CASE WHEN p.{$type}_id = {$value} THEN p.points END), 2) AS :{$label}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
    }

    /**
     * validate and return the relevant order query to sort the results
     *
     * @return string
     */
    private function getOrderBy(): string
    {
        $orderByName = "u.lastname {$this->order}, COALESCE(u.preferred_name, u.firstname) {$this->order}";

        if (strcasecmp($this->sortBy, 'total') === 0) {
            return "total {$this->order}";
        }

        if (!str_starts_with($this->sortBy, 'count_')) {
            return $orderByName;
        }

        if (
            !in_array($this->sortBy, $this->helper->getType()->map(fn(Type $type) => "count_type_" . $type->getId())->getAll()) &&
            !in_array($this->sortBy, $this->helper->getSubTypes()->map(fn(SubType $subtype) => "count_subtype_" . $subtype->getId())->getAll())
        ) {
            return "{$this->sortBy} {$this->order}";
        }

        return $orderByName;
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

        $whereTypes = $this->helper->getType();

        if ($whereTypes->count() > 0) {
            $whereTypeIds = implode(',', $whereTypes->getPropertyValues('id'));
            $countTypeQuery = implode(
                ', ',
                $whereTypes->map(
                    fn(Type $type) => $this->getSelectCountString($this->resultType, $type->getId())
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
        }

        $whereSubTypes = $this->helper->getSubTypes();
        if (($whereSubTypes)->count() > 0) {
            $whereSubTypeIds   = implode(',', $this->helper->getSubTypes()->getPropertyValues('id'));
            $countSubTypeQuery = implode(
                ', ',
                $this->helper->getSubTypes()->map(
                    fn(SubType $subType) => $this->getSelectCountString($this->resultType, $subType->getId(), true)
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
        }

        $countQuery = implode(', ', array_filter([$countTypeQuery ?? '', $countSubTypeQuery ?? '']));

        $whereTypeSubTypeQuery = implode(" OR ", $whereInTypeSubTypeQuery);

        $whereQuery = implode(" AND ", $this->helper->getFilters());

        $countQuery =
            "SELECT p.student_id, {$countQuery}
            {$this->helper->getFromString()}
            WHERE
                ({$whereTypeSubTypeQuery}) AND
                {$whereQuery} AND
                p.deleted_at IS NULL
            GROUP BY p.student_id
           ";

        $typeKeys = implode(
            ' + ',
            $whereTypes->map(
                fn(Type $type) => "COALESCE(count_type_{$type->getId()}, 0)"
            )->getAll()
        );

        $subTypeKeys = implode(
            ' + ',
            $this->helper->getSubTypes()->map(
                fn(SubType $subType) => "COALESCE(count_subtype_{$subType->getId()}, 0)"
            )->getAll()
        );

        $typeNullValueCheck = $this->helper->getType()->map(
            fn(Type $type) => "count_type_{$type->getId()} IS NULL"
        )->getAll();

        $subTypeNullValueCheck = $this->helper->getSubTypes()->map(
            fn(SubType $subType) => "count_subtype_{$subType->getId()} IS NULL"
        )->getAll();

        $totalCount = "CASE WHEN ";
        $totalCount .= implode(" AND ", array_filter($typeNullValueCheck + $subTypeNullValueCheck));
        $totalCount .= " THEN NULL ELSE ";
        $totalCount .= "(" . implode(' + ', array_filter([$typeKeys, $subTypeKeys])) . ") END as total";

        /**
         * Finding out where to locate the position where the cursor is pointing
         */
        $whereParts = "";
        $this->params['limit'] = $this->limit;
        $groupBy = "u.lastname, COALESCE(u.preferred_name, u.firstname)";

        $orderBy = $this->getOrderBy();

        if (isset($cursor)) {
            $sortingOperator = $this->order == Cursor::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            $whereParts = "
                CROSS JOIN (SELECT id, lastname, COALESCE(preferred_name, firstname) as preferred_name
                FROM user
                WHERE id = :cursor) u2
                WHERE (
                    u.lastname {$sortingOperator} u2.lastname OR
                    (u.lastname {$sortingOperator} u2.lastname AND COALESCE(u.preferred_name, u.firstname) {$sortingOperator} u2.preferred_name))
            ";
            $this->params['cursor'] = $cursor['id'];
        }

        $totalQuery = "
            SELECT countData.*, {$totalCount}
            FROM ($countQuery) countData
            INNER JOIN user u ON countData.student_id = u.id
            {$whereParts}
            GROUP BY countData.student_id, {$groupBy}
            ORDER BY {$orderBy}
            LIMIT :limit
        ";

        $result =  $db->executeQuery(
            $totalQuery,
            array_merge(
                $this->params,
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
        $whereInTypeSubTypeQuery = [];
        if ($this->helper->getType()->count() > 0) {
            $whereTypeIds = implode(',', $this->helper->getType()->getPropertyValues('id'));
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
        }
        if ($this->helper->getSubTypes()->count() > 0) {
            $whereSubTypeIds = implode(',', $this->helper->getSubTypes()->getPropertyValues('id'));
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
        }

        $whereQuery = implode(" OR ", array_filter($whereInTypeSubTypeQuery));
        $whereFilterQuery = implode(" AND ", $this->helper->getFilters());

        $query = "
            SELECT p.student_id
            {$this->helper->getFromString()}
            WHERE
                {$whereQuery}
                AND {$whereFilterQuery}
                AND p.deleted_at IS NULL
            GROUP BY p.student_id
        ;";

        $result = $this->pastoralMapper->getDatabase()->executeQuery(
            $query,
            $this->helper->getParams()
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
        ]);
    }

    /**
     * Get the header row for the table
     *
     * @return array[]
     */
    private function getTableHeaders(): array
    {
        $types = $this->helper->getType();

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

        foreach ($this->helper->getSubTypes() as $subType) {
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
            $keys = array_keys($records->first());
            array_shift($keys);

            $totalRow['user'] = 'Total';

            /* Return null if all the values are null otherwise return the total count
             */
            foreach ($keys as $key) {
                $totalRow[$key] = [
                    $this->resultType => array_reduce(
                        array_column($records->getAll(), $key),
                        function (?float $carry, ?float $value) {
                            if (!is_null($value)) {
                                return $carry += $value;
                            }
                            return $carry;
                        }
                    )];
            }

            $rows = $records->map(
                function (array $record) use ($students) {
                    $row['user'] = call_user_func($this->userTranslator, $students->getBy('id', $record['student_id']));
                    unset($record['student_id']);
                    return array_merge($row, array_map(fn($value) => [ $this->resultType => $value], ($record)));
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
