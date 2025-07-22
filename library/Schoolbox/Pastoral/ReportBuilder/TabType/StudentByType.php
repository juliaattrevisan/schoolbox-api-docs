<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Alaress_Date_Format;
use Schoolbox\UserMapper;

class StudentByType extends Cursor implements TabTypeInterface
{
    public function retrieveResults(?string $encodedCursor): Collection
    {
        return  $this->getResults($this->report);
    }

    private function getTableHeaders()
    {
        $types = $this->report->getTypes()->diff(
            $this->report->getSubTypes()?->map(
                fn(SubType $subType) => $subType->getPastoralType()
            )
        );

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

        foreach ($this->report->getSubTypes() as $subType) {
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


    public function process(Collection $records)
    {
        $tableHeader = $this->getTableHeaders();

        $userMapper = UserMapper::getInstance();

        $students = $userMapper->findByColumn('id', array_column($records->getAll(), 'student_id'));

        $keys = array_keys($records->first());
        array_shift($keys);

        $totalRow['user'] = 'Total';

        foreach ($keys as $key) {
            $totalRow[$key] = ['records' => array_sum(array_column($records->getAll(), $key))];
        }

        return [
            'data' => [
                'tableHeaders' => $tableHeader,
                'totalRow' => $totalRow,
                'rows' => $records->map(
                    function (array $record) use ($students) {
                        $row['user'] = call_user_func($this->userTranslator, $students->getBy('id', $record['student_id']));
                        unset($record['student_id']);
                        return array_merge($row, array_map(fn($value) => ['records' => (int)$value], ($record)));
                    }
                )
            ],
            'metadata' => [
                'count' => $this->retrieveResultCount(),
                'cursor' => [
                    'current' => $this->current($records),
                    'next' => $this->next($records) ?? null,
                ]
            ]
        ];
    }

    public function getResults(PastoralReport $report)
    {
        $pastoralMapper = Mapper::getInstance();
        $termMapper = \Schoolbox\Assessment\Term\Mapper::getInstance();

        // Special case for the current term relative date, which is not a strtotime-able thingy
        if (!$report->getUsesRelativeDate()) {
            $beforeDate = $report->getBeforeDate();
            $afterDate  = $report->getAfterDate();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_TERM) {
            $currentTerm = $termMapper->findForToday();
            $beforeDate  = $currentTerm->getActualFinish();
            $afterDate   = $currentTerm->getStart();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_YEAR) {
            $now        = Alaress_Date_Format::now();
            $beforeDate = (clone $now)->endOfYear();
            $afterDate  = (clone $now)->startOfYear();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_SCHOOL_YEAR) {
            $now        = Alaress_Date_Format::now();
            $beforeDate = (clone $now)->endOfSchoolYear();
            $afterDate  = (clone $now)->getStartOfSchoolYear();
        } else {
            $beforeDate = Alaress_Date_Format::now();
            $afterDate  = Alaress_Date_Format::newFromTimestamp(strtotime($report->getRelativeDateString()));
        }

        $db = $pastoralMapper->getDatabase();

        $whereInTypeSubTypeQuery = [];
        $selectCountTypeQuery = [];

        $whereTypes = $report->getTypes()->diff(
            $report->getSubTypes()?->map(
                fn(SubType $subType) => $subType->getPastoralType()
            )
        );

        if ($whereTypes->count() > 0) {
            $whereTypeIds = implode(',', Collection::arrayColumn($whereTypes, 'id'));
            $countTypeQuery = implode(
                ', ',
                $whereTypes->map(
                    fn(Type $type) => "COUNT(CASE WHEN p.type_id = {$type->getId()} THEN 1 END) AS count_type_{$type->getId()}"
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.type_id IN ({$whereTypeIds})";
            $selectCountTypeQuery[] = implode(
                ', ',
                $whereTypes->map(
                    fn(Type $type) => "count_type_{$type->getId()}"
                )->getAll()
            );
        }

        $whereSubTypes = $report->getSubTypes() ?? [];
        if (!empty($whereSubTypes)) {
            $whereSubTypeIds   = implode(',', Collection::arrayColumn($report->getSubTypes(), 'id'));
            $countSubTypeQuery = implode(
                ', ',
                $report->getSubTypes()->map(
                    fn(SubType $subType) => "COUNT(CASE WHEN p.subtype_id = {$subType->getId()} THEN 1 END) AS count_subtype_{$subType->getId()}"
                )->getAll()
            );
            $whereInTypeSubTypeQuery[] = "p.subtype_id IN ({$whereSubTypeIds})";
            $selectCountTypeQuery[] = implode(
                ', ',
                $whereSubTypes->map(
                    fn(SubType $subtype) => "count_subtype_{$subtype->getId()}"
                )->getAll()
            );
        }

        $countQuery = implode(', ', array_filter([$countTypeQuery ?? '', $countSubTypeQuery ?? '']));

        $whereTypeSubTypeQuery = implode(" OR ", $whereInTypeSubTypeQuery);

        $countQuery =
            "SELECT p.student_id, {$countQuery}
            FROM {$pastoralMapper->getTable()} p
            WHERE
                ({$whereTypeSubTypeQuery}) AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) <= :beforeDate) AND
                (COALESCE(`p`.`occurred_on`, `p`.`created_at`) >= :afterDate)
            GROUP BY p.student_id
            LIMIT :limit
           ";

        $typeKeys = implode(
            ' + ',
            $whereTypes->map(
                fn(Type $type) => "count_type_{$type->getId()}"
            )->getAll()
        );

        $subTypeKeys = implode(
            ' + ',
            $report->getSubTypes()->map(
                fn(SubType $subType) => "count_subtype_{$subType->getId()}"
            )->getAll()
        );

        $totalCount = "SUM(" . implode(' + ', array_filter([$typeKeys, $subTypeKeys])) . ") as total";

        $selectQuery = implode(', ', array_filter($selectCountTypeQuery));
        $totalQuery = "
            SELECT countData.student_id, {$totalCount}, {$selectQuery}
            FROM ($countQuery) countData
            GROUP BY countData.student_id
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
}
