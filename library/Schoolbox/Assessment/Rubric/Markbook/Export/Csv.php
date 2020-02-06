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

        $this->exportHeader();
        foreach ($this->assessments as $assessmentId => $assessment) {
            $this->exportAssessment($assessmentId, $assessment);
        }
        $this->exportOverallMarks();

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


    /**
     * Export the header row for this CSV.
     */
    private function exportHeader(): void
    {
        $this->exportRow(
            ['Assessment', 'Capability', 'Indicator'],
            array_map(function ($student) {
                return $this->exportStudentName($student);
            }, $this->students)
        );
    }


    /**
     * Export all rows for a single assessment for this CSV.
     *
     * @param int   $assessmentId
     * @param array $assessment
     */
    private function exportAssessment(int $assessmentId, array $assessment): void
    {
        $assessmentName = $assessment['name'];
        foreach ($assessment['capabilities'] as $capabilityId => $capability) {
            $capabilityName = $capability['name'];

            // Output mark-per-capability, only if using it
            $capabilityMarks = [];
            if ($assessment['usesMarkPerCap']) {
                foreach ($this->students as $studentId => $student) {
                    $excluded = $this->exclusions[$assessmentId][$studentId] ?? false;
                    if (!$excluded) {
                        $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] ?? '-';
                    } else {
                        $studentMark = '(excluded)';
                    }
                    $capabilityMarks[$studentId] = $studentMark;
                }
                $this->exportRow(
                    [$assessmentName, $capabilityName, '(capability mark)'],
                    $capabilityMarks
                );
            }

            // Output indicator marks
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $indicatorName = $indicator['name'];

                // Put together assessment data
                $indicatorMarks = [];
                foreach ($this->students as $studentId => $student) {
                    $excluded = $this->exclusions[$assessmentId][$studentId] ?? false;
                    if (!$excluded) {
                        $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] ?? '-';
                    } else {
                        $studentMark = '(excluded)';
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


    /**
     * Export overall mark rows for this CSV.
     */
    private function exportOverallMarks(): void
    {
        foreach ($this->overallMarks as $capabilityId => $capability) {
            $overallHeader  = '(overall)';
            $capabilityName = $capability['name'];

            if ($capability['show']) {
                $capabilityMarks = [];
                foreach ($this->students as $studentId => $student) {
                    $capabilityMarks[$studentId] = $capability['marks'][$studentId]['humanReadable'] ?? '-';
                }
                $this->exportRow(
                    [$overallHeader, $capabilityName, '(capability mark)'],
                    $capabilityMarks
                );
            }
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $indicatorName  = $indicator['name'];
                $indicatorMarks = [];
                foreach ($this->students as $studentId => $student) {
                    $indicatorMarks[$studentId] = $indicator['marks'][$studentId]['humanReadable'] ?? '-';
                }
                $this->exportRow(
                    [$overallHeader, $capabilityName, $indicatorName],
                    $indicatorMarks
                );
            }
        }
    }
}
