<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;

class StudentByType extends Cursor implements TabTypeInterface
{
    public const COLUMN_KEY_FORMAT = "count_%s_%d";
    public const COLUMN_KEY_REGEX = "/count_(?<type>\w+)_(?<id>\d+)/";

    private array $params = [];

    private ?Collection $typeSubTypeKeys = null;
    /**
     * Get the select string for the counts
     *
     * @param string       $resultType
     * @param Type|SubType $type
     * @return string
     * @throws \Exception
     */
    private function getSelectCountString(string $resultType, Type|SubType $type): string
    {
        $typeKey = ($type instanceof Type) ? 'type' : 'subtype';
        $value = $type->getId();

        $label = sprintf(self::COLUMN_KEY_FORMAT, $typeKey, $value);
        $this->params[$label] = $label;

        return match (strtolower($resultType)) {
            Cursor::RESULT_TYPE_RECORDS =>
            "ROUND(COUNT(CASE WHEN p.{$typeKey}_id = {$value} THEN 1 END), 2) AS :{$label}",
            Cursor::RESULT_TYPE_POINTS =>
            "ROUND(SUM(CASE WHEN p.{$typeKey}_id = {$value} THEN p.points END), 2) AS :{$label}",
            default => throw new \Exception("Unknown result type: '{$resultType}'"),
        };
    }

    private function getTypeSubtypeKey(): Collection
    {
        if (null === $this->typeSubTypeKeys) {
            $this->typeSubTypeKeys = new Collection(array_filter(array_merge(
                $this->helper->getType()->map(fn(Type $type) => sprintf(self::COLUMN_KEY_FORMAT, 'type', $type->getId()))->getAll(),
                $this->helper->getSubTypes()->map(fn(SubType $subtype) => sprintf(self::COLUMN_KEY_FORMAT, 'subtype', $subtype->getId()))->getAll()
            )));
        }

        return $this->typeSubTypeKeys;
    }

    /**
     * validate and return the relevant order query to sort the results
     *
     * @return string
     */
    private function getOrderBy(): string
    {
        if (strcasecmp($this->sortBy, 'total') === 0) {
            return "total {$this->order}";
        }

        if (str_starts_with($this->sortBy, 'count_')) {
            /* Double-checking that the key is valid column for sorting
             */
            if (
                in_array($this->sortBy, $this->helper->getType()->map(fn(Type $type) => sprintf(self::COLUMN_KEY_FORMAT, 'type', $type->getId()))->getAll()) ||
                in_array($this->sortBy, $this->helper->getSubTypes()->map(fn(SubType $subtype) => sprintf(self::COLUMN_KEY_FORMAT, 'subtype', $subtype->getId()))->getAll())
            ) {
                return "{$this->sortBy} {$this->order}";
            }
        }

        /* Otherwise default to sorting by name
         */
        return "u.lastname {$this->order}, COALESCE(u.preferred_name, u.firstname) {$this->order}";
    }

    private function getTotalCount(): Collection
    {
        $db = $this->pastoralMapper->getDatabase();

        $selectSUM = implode(',', $this->getTypeSubtypeKey()->map(fn(string $key) => "SUM({$key}) as {$key}")->getAll());

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

    private function getResultQuery(): string
    {
        $whereInTypeSubTypeQuery = [];

        $whereTypes = $this->helper->getType();

        if ($whereTypes->count() > 0) {
            $whereTypeIds = implode(',', $whereTypes->getPropertyValues('id'));
            $countTypeQuery = implode(
                ', ',
                $whereTypes->map(
                    fn(Type $type) => $this->getSelectCountString($this->resultType, $type)
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
                    fn(SubType $subType) => $this->getSelectCountString($this->resultType, $subType)
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
                fn(Type $type) => sprintf("COALESCE(" . self::COLUMN_KEY_FORMAT . ", 0)", 'type', $type->getId())
            )->getAll()
        );

        $subTypeKeys = implode(
            ' + ',
            $this->helper->getSubTypes()->map(
                fn(SubType $subType) => sprintf("COALESCE(" . self::COLUMN_KEY_FORMAT . ", 0)", 'subtype', $subType->getId())
            )->getAll()
        );

        $typeNullValueCheck = $this->helper->getType()->map(
            fn(Type $type) => sprintf(self::COLUMN_KEY_FORMAT . " IS NULL", 'type', $type->getId())
        )->getAll();

        $subTypeNullValueCheck = $this->helper->getSubTypes()->map(
            fn(SubType $subType) => sprintf(self::COLUMN_KEY_FORMAT . " IS NULL", 'subtype', $subType->getId())
        )->getAll();

        $totalCount = "CASE WHEN ";
        $totalCount .= implode(" AND ", array_filter($typeNullValueCheck + $subTypeNullValueCheck));
        $totalCount .= " THEN NULL ELSE ";
        $totalCount .= "(" . implode(' + ', array_filter([$typeKeys, $subTypeKeys])) . ") END as total";

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
        $params['limit'] = $this->limit;
        $groupBy = "u.lastname, COALESCE(u.preferred_name, u.firstname)";

        $orderBy = $this->getOrderBy();

        if (isset($cursor)) {
            $sortingOperator = $this->order == Cursor::ORDER_DIRECTION_DEFAULT ? ' > ' : ' < ';
            if (key_exists('count', $cursor) && ($this->getTypeSubtypeKey()->contains($this->sortBy) | strcasecmp('total', $this->sortBy) === 0)) {
                $whereParts = "
                WHERE (
                          (withTotal.student_id {$sortingOperator} :studentId AND {$this->sortBy} = :countDiscriminator) OR
                          ({$this->sortBy} {$sortingOperator} :countDiscriminator)
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

        $totalQuery = "
            SELECT *
            FROM (
                    {$this->getResultQuery()}
                ) withTotal
            INNER JOIN user u ON withTotal.student_id = u.id
            {$whereParts}
            GROUP BY withTotal.student_id, {$groupBy}
            ORDER BY {$orderBy}, withTotal.student_id {$this->order}
            LIMIT :limit
        ";

        $result =  $db->executeQuery(
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

        /* If result size is smaller than limit, assumed that it is at the end of pointer and terminate early
         */
        if ($collection->count() < $this->limit) {
            return null;
        }

        $last = $collection->last();

        $discriminator = [ 'id' => $last['student_id']];

        if ($this->getTypeSubtypeKey()->contains($this->sortBy) | strcasecmp('total', $this->sortBy) == 0) {
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
            ]
        ];

        foreach ($this->helper->getType() as $type) {
            $tableHeaders[] = [
                'key' => sprintf(self::COLUMN_KEY_FORMAT, 'type', $type->getId()),
                'title' => $type->getName(),
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ];
        }

        foreach ($this->helper->getSubTypes() as $subType) {
            if ($subType instanceof SubType) {
                $tableHeaders[] = [
                    'key'   => sprintf(self::COLUMN_KEY_FORMAT, 'subtype', $subType->getId()),
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
            $totalRow = array_merge(
                $totalRow,
                $this->getTotalCount()
                     ->map(fn($count) => [ $this->resultType => is_numeric($count) ? $count / 1 : null ])->getAll()
            );

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
