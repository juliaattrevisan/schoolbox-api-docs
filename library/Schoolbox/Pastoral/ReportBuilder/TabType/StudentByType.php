<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper;
use Schoolbox\Pastoral\Model as PastoralRecord;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\Cursor;
use Schoolbox\Pastoral\SubType\Model as SubType;
use Schoolbox\Pastoral\Type\Model as Type;
use Schoolbox\Pastoral\Type\TypeAbstract;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User\Translator\Author\ModelToArray;
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

        foreach($keys as $key) {
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

        $selectQuery = implode(', ', array_filter($selectCountTypeQuery ?? []));
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

    public function getResults1(PastoralReport $report)
    {
        $pastoralMapper = Mapper::getInstance();

        $userAccessMapper = UserAccessMapper::getInstance();

        $termMapper = \Schoolbox\Assessment\Term\Mapper::getInstance();
//
//        $db = $pastoralMapper->getDatabase();
//
//        $test = $db->executeQuery("
//            SELECT *
//            FROM {$pastoralMapper->getTable()}
//        ");
//
//        $test3 = $test->fetchAll(\PDO::FETCH_ASSOC);
//
//        $test2 = array_map(
//            fn ($record) => [
//                'id' => $record['id'],
//            ],
//            $test3
//        );

        $pb = $pastoralMapper->getPredicateBuilder();

        $whereTypes = $report->getTypes()->diff(
            $report->getSubTypes()?->map(
                fn(SubType $subType) => $subType->getPastoralType()
            )
        );

        $whereSubTypes = $report->getSubTypes();

        $pb->any(
            $pastoralMapper->getPredicateBuilder()
                           ->in('type_id', Collection::arrayColumn($whereTypes, 'id'))
                           ->in('subtype_id', Collection::arrayColumn($whereSubTypes, 'id'))
        );

        $years = $report->getYears();
        $pb->in('s.year_id', Collection::arrayColumn($years, 'id'));

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

        $pb->rawWhere(
            '(COALESCE(`pastoral`.`occurred_on`, `pastoral`.`created_at`) <= :beforeDate)',
            [':beforeDate' => $beforeDate->endOfDay()->toIso8601String()]
        );

        $pb->rawWhere(
            '(COALESCE(`pastoral`.`occurred_on`, `pastoral`.`created_at`) >= :afterDate)',
            [':afterDate' => $afterDate->endOfDay()->toIso8601String()]
        );

        $result = $pastoralMapper->findByPredicates($pb);

        return $result;
    }

    public function process2($records)
    {
        // Extract unique types and subtypes from the records in a single pass
        $types = [];
        $subtypes = [];
        $typesWithSubtypes = [];

        /** @var PastoralRecord $record */
        foreach ($records as $record) {
            $type = $record->getType();
            if ($type) {
                $typeId = $type->getId();
                // add type to the list if it doesn't already exist
                if (!isset($types[$typeId])) {
                    $types[$typeId] = $type;
                }
            }

            $subtype = $record->getSubType();
            if ($subtype) {
                $subtypeId = $subtype->getId();
                // add subtype to the list if it doesn't already exist
                if (!isset($subtypes[$subtypeId])) {
                    $subtypes[$subtypeId] = $subtype;
                    // Track which types have subtypes for later processing
                    $typesWithSubtypes[$subtype->getPastoralType()->getId()] = true;
                }
            }
        }

        // Calculate counts and points for all types/subtypes and group by student in a single pass
        $subtypeCounts = [];
        $subtypePoints = [];
        $subtypeHasPoints = []; // Track if any records for this subtype have points set
        $typeCounts = [];
        $typePoints = [];
        $typeHasPoints = []; // Track if any records for this type have points set
        $studentRecords = [];

        foreach ($records as $record) {
            $student = $record->getStudent();
            $studentId = $student->getId();
            $points = $record->getPoints();
            $pointsValue = ($points !== null && $points !== '') ? (int)$points : 0;

            // Initialize student record if not exists
            if (!isset($studentRecords[$studentId])) {
                $studentRecords[$studentId] = [
                    'student' => $student,
                    'subtypeCounts' => [],
                    'subtypePoints' => [],
                    'subtypeHasPoints' => [], // Track if any records for this student/subtype have points set
                    'typeCounts' => [],
                    'typePoints' => [],
                    'typeHasPoints' => [], // Track if any records for this student/type have points set
                ];
            }

            // Process subtypes
            $subtype = $record->getSubType();
            if ($subtype) {
                $subtypeId = $subtype->getId();
                $hasPoints = ($points !== null && $points !== '');

                // Increment global counts
                $subtypeCounts[$subtypeId] = ($subtypeCounts[$subtypeId] ?? 0) + 1;
                $subtypePoints[$subtypeId] = ($subtypePoints[$subtypeId] ?? 0) + $pointsValue;
                if ($hasPoints) {
                    $subtypeHasPoints[$subtypeId] = true;
                }

                // Increment student counts
                $studentRecords[$studentId]['subtypeCounts'][$subtypeId] =
                    ($studentRecords[$studentId]['subtypeCounts'][$subtypeId] ?? 0) + 1;
                $studentRecords[$studentId]['subtypePoints'][$subtypeId] =
                    ($studentRecords[$studentId]['subtypePoints'][$subtypeId] ?? 0) + $pointsValue;
                if ($hasPoints) {
                    $studentRecords[$studentId]['subtypeHasPoints'][$subtypeId] = true;
                }
            }

            // Process types (only those without subtypes)
            $type = $record->getType();
            if ($type && !isset($typesWithSubtypes[$type->getId()])) {
                $typeId = $type->getId();
                $hasPoints = ($points !== null && $points !== '');

                // Increment global counts
                $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
                $typePoints[$typeId] = ($typePoints[$typeId] ?? 0) + $pointsValue;
                if ($hasPoints) {
                    $typeHasPoints[$typeId] = true;
                }

                // Increment student counts
                $studentRecords[$studentId]['typeCounts'][$typeId] =
                    ($studentRecords[$studentId]['typeCounts'][$typeId] ?? 0) + 1;
                $studentRecords[$studentId]['typePoints'][$typeId] =
                    ($studentRecords[$studentId]['typePoints'][$typeId] ?? 0) + $pointsValue;
                if ($hasPoints) {
                    $studentRecords[$studentId]['typeHasPoints'][$typeId] = true;
                }
            }
        }

        // Sort types by sequence and build table columns in the correct order
        $typesSorted = [];
        foreach ($types as $type) {
            $typesSorted[$type->getSequence()] = $type;
        }
        ksort($typesSorted);

        // Build table columns and total row data arrays
        $tableColumns = [];
        $totalRowData = [];

        foreach ($typesSorted as $type) {
            $typeId = $type->getId();

            if (isset($typesWithSubtypes[$typeId])) {
                // This type has subtypes - sort and add all subtypes for this type
                $typeSubtypes = [];
                foreach ($subtypes as $subtype) {
                    if ($subtype->getPastoralType()->getId() === $typeId) {
                        $typeSubtypes[$subtype->getSequence()] = $subtype;
                    }
                }
                ksort($typeSubtypes);

                foreach ($typeSubtypes as $subtype) {
                    $subtypeId = $subtype->getId();
                    $tableColumns[] = [
                        'key' => 'subtype-' . $subtypeId,
                        'title' => $subtype->getPastoralType()->getName(),
                        'subTitle' => $subtype->getName(),
                        'type' => 'RecordsAndPoints',
                        'isSortable' => true,
                    ];

                    $totalRowItem = ['records' => $subtypeCounts[$subtypeId] ?? 0];
                    if (isset($subtypeHasPoints[$subtypeId])) {
                        $totalRowItem['points'] = $subtypePoints[$subtypeId] ?? 0;
                    }
                    $totalRowData[] = $totalRowItem;
                }
            } else {
                // This type has no subtypes - add the type itself
                $tableColumns[] = [
                    'key' => 'type-' . $typeId,
                    'title' => $type->getName(),
                    'type' => 'RecordsAndPoints',
                    'isSortable' => true,
                ];

                $totalRowItem = ['records' => $typeCounts[$typeId] ?? 0];
                if (isset($typeHasPoints[$typeId])) {
                    $totalRowItem['points'] = $typePoints[$typeId] ?? 0;
                }
                $totalRowData[] = $totalRowItem;
            }
        }

        // Build table headers structure
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
        ];
        $tableHeaders = array_merge($tableHeaders, $tableColumns);

        // Calculate grand totals for the summary row
        $grandTotalRecords = array_sum($subtypeCounts) + array_sum($typeCounts);
        $grandTotalPoints = array_sum($subtypePoints) + array_sum($typePoints);

        // Build total row structure
        $totalRow = [
            'Total', // Sits in first column along with User
        ];

        $grandTotalItem = ['records' => $grandTotalRecords];
        if (!empty($subtypeHasPoints) || !empty($typeHasPoints)) {
            $grandTotalItem['points'] = $grandTotalPoints;
        }
        $totalRow[] = $grandTotalItem;

        $totalRow = array_merge($totalRow, $totalRowData);

        // Build student rows with data matching the column order
        $studentRows = [];
        foreach ($studentRecords as $studentData) {
            $student = $studentData['student'];

            // Use the user translator to get standardized user data
            $userData = call_user_func($this->userTranslator, $student);

            // First column user data from translator
            $row = [$userData];

            // Next, add total column
            $totalRecords = array_sum($studentData['subtypeCounts']) + array_sum($studentData['typeCounts']);
            $totalPoints = array_sum($studentData['subtypePoints']) + array_sum($studentData['typePoints']);

            $totalItem = ['records' => $totalRecords];
            if (!empty($studentData['subtypeHasPoints']) || !empty($studentData['typeHasPoints'])) {
                $totalItem['points'] = $totalPoints;
            }
            $row[] = $totalItem;

            // Add data for each column in the same order as tableColumns
            foreach ($typesSorted as $type) {
                $typeId = $type->getId();

                if (isset($typesWithSubtypes[$typeId])) {
                    // Add subtypes for this type in sequence order
                    $typeSubtypes = [];
                    foreach ($subtypes as $subtype) {
                        if ($subtype->getPastoralType()->getId() === $typeId) {
                            $typeSubtypes[$subtype->getSequence()] = $subtype;
                        }
                    }
                    ksort($typeSubtypes);

                    foreach ($typeSubtypes as $subtype) {
                        $subtypeId = $subtype->getId();
                        $studentSubtypeItem = ['records' => $studentData['subtypeCounts'][$subtypeId] ?? 0];
                        if (isset($studentData['subtypeHasPoints'][$subtypeId])) {
                            $studentSubtypeItem['points'] = $studentData['subtypePoints'][$subtypeId] ?? 0;
                        }
                        $row[] = $studentSubtypeItem;
                    }
                } else {
                    // Add data for standalone type
                    $studentTypeItem = ['records' => $studentData['typeCounts'][$typeId] ?? 0];
                    if (isset($studentData['typeHasPoints'][$typeId])) {
                        $studentTypeItem['points'] = $studentData['typePoints'][$typeId] ?? 0;
                    }
                    $row[] = $studentTypeItem;
                }
            }

            $studentRows[] = $row;
        }

        // Sort student rows alphabetically by last name
        usort($studentRows, function ($a, $b) {
            return strcmp($a[0]['lastName'], $b[0]['lastName']);
        });

        /**
         * TODO this is temporary and should create a cursor to return the data properly with pagination
         * it will be done later in the epic
         */
        // Data payload with calculated values
        return [
            'data' => [
                'tableHeaders' => $tableHeaders,
                'totalRow' => $totalRow,
                'rows' => $studentRows,
            ],
            'metadata' => [
                'count' => 20,
                'cursor' => [
                    'current' => null,
                    'next' => null,
                ],
            ],
        ];
    }
}
