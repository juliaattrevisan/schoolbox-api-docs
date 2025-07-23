<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Alaress_Date_Format;

class StudentByType extends Cursor implements TabTypeInterface
{
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

    /**
     * @inheritdoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);
        }

        $beforeDate = $this->report['beforeDate'];
        $afterDate = $this->report['afterDate'];

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

        $countQuery =
            "SELECT p.student_id, {$countQuery}
            FROM {$this->pastoralMapper->getTable()} p
            LEFT JOIN user u ON p.student_id = u.id
            WHERE
                ({$whereTypeSubTypeQuery}) AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) <= :beforeDate) AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) >= :afterDate)
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
            [
                'beforeDate' => $beforeDate->endOfDay()->toIso8601String(),
                'afterDate'  => $afterDate->startOfDay()->toIso8601String(),
                'limit' => $this->limit,
            ]
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

        $query = "
            SELECT p.student_id
            FROM {$this->pastoralMapper->getTable()} p
            WHERE
                {$whereQuery} AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) <= :beforeDate) AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) >= :afterDate)
            GROUP BY p.student_id
        ;";

        $result = $this->pastoralMapper->getDatabase()->executeQuery(
            $query,
            [
                'beforeDate' => $this->report['beforeDate']->endOfDay()->toIso8601String(),
                'afterDate'  => $this->report['afterDate']->startOfDay()->toIso8601String(),
            ]
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
