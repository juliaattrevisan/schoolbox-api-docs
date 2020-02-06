<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook\Export;

use Schoolbox\Learning\Markbook\AbstractCSV;

/**
 * Output a set of assessments as a csv
 */
class Csv extends AbstractCSV
{
    /**
     * A list of assessment data.
     *
     * @var array
     */
    private $students;


    /**
     * A list of assessment data.
     *
     * @var array
     */
    private $assessments;


    /**
     * A list of IDs of students excluded from each assessment, grouped by
     * assessment ID.
     *
     * @var int[][]
     */
    private $exclusions;


    /**
     * A summation of marks for each capability.
     *
     * @var array
     */
    private $overallMarks;


    /**
     * Constructor
     *
     * @param array $students
     * @param array $assessments
     * @param array $exclusions
     * @param array $overallMarks
     */
    public function __construct(
        array $students,
        array $assessments,
        array $exclusions,
        array $overallMarks
    ) {
        $this->students     = $students;
        $this->assessments  = $assessments;
        $this->exclusions   = $exclusions;
        $this->overallMarks = $overallMarks;
    }


    /**
     * @inheritdoc
     */
    public function export(string $filename): void
    {
        $this->initExport($filename);

        // Header: a list of students
        $this->exportRow(
            ['Assessment', 'Capability', 'Indicator'],
            array_map(function ($student) { return $this->exportStudentName($student); }, $this->students)
        );

        // Each row: assessment, capability/indicator
        foreach ($this->assessments as $assessmentId => $assessment) {
            $assessmentName = $assessment['name'];
            foreach ($assessment['capabilities'] as $capabilityId => $capability) {
                $capabilityName = $capability['name'];

                // Output mark-per-capability
                $capabilityMarks = [];
                if ($assessment['usesMarkPerCap']) {
                    foreach ($this->students as $studentId => $student) {
                        $excluded    = $this->exclusions[$assessmentId][$studentId] ?? false;
                        $studentMark = '-';
                        if (!$excluded) {
                            $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] ?? '-';
                        }
                        $capabilityMarks[$studentId] = $studentMark;
                    }
                } else {
                    $capabilityMarks = array_fill(0, count($this->students), '-');
                }
                $this->exportRow(
                    [$assessmentName, $capabilityName, '-'],
                    $capabilityMarks
                );
                foreach ($capability['indicators'] as $indicatorId => $indicator) {
                    $indicatorName = $indicator['name'];

                    // Put together assessment data
                    $indicatorMarks = [];
                    foreach ($this->students as $studentId => $student) {
                        $excluded    = $this->exclusions[$assessmentId][$studentId] ?? false;
                        $studentMark = '-';
                        if (!$excluded) {
                            $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] ?? '-';
                        }
                        $indicatorMarks[$studentId] = $studentMark;
                    }
                    $this->exportRow(
                        [$assessmentName, $capabilityName, $indicatorName],
                        $indicatorMarks
                    );
                }
            }
        }

        $this->closeExport();
    }


    /**
     * Helper for exporting a student's name.
     *
     * @param array $student
     * @return string
     */
    private function exportStudentName(array $student): string
    {
        $nameParts = [$student['lastname'] ?? '', $student['firstname'] ?? ''];
        return implode(', ', array_filter($nameParts));
    }
}
