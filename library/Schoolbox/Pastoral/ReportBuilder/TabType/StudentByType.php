<?php

namespace Schoolbox\Pastoral\ReportBuilder\TabType;

use Schoolbox\Pastoral\Model as PastoralRecord;
use Schoolbox\User\Translator\Author\ModelToArray;

class StudentByType implements TabTypeInterface
{
    public function __construct(
        private ModelToArray $userTranslator,
    )
    {
    }

    public function process($records)
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
