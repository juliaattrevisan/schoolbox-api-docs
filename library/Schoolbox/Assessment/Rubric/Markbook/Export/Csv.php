<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook\Export;

use Schoolbox\L10n\RosettaStone as Translator;
use Schoolbox\Learning\Markbook\AbstractCSV;

/**
 * Output a set of assessments as a csv.
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
     * @var Translator
     */
    private $translator;


    /**
     * Constructor
     *
     * @param array      $students
     * @param array      $assessments
     * @param array      $exclusions
     * @param array      $overallMarks
     * @param Translator $translator
     */
    public function __construct(
        array $students,
        array $assessments,
        array $exclusions,
        array $overallMarks,
        Translator $translator
    ) {
        $this->students     = $students;
        $this->assessments  = $assessments;
        $this->exclusions   = $exclusions;
        $this->overallMarks = $overallMarks;
        $this->translator   = $translator;
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
            [
                $this->translator->translate('assessment', false, true),
                $this->translator->translate('rubricGroup', false, true),
                $this->translator->translate('rubricCriterion', false, true),
            ],
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

            // Output capability marks only if this assessment uses them
            if ($assessment['usesMarkPerCap']) {
                $capabilityMarks = $this->retrieveAssessmentCapabilityMarks($assessmentId, $capabilityId);
                $this->exportRow(
                    [$assessmentName, $capabilityName, $this->translator->translateSentence('({:rubricGroup} mark)')],
                    $capabilityMarks
                );
            }

            // Output indicator marks
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $this->exportRow(
                    [$assessmentName, $capabilityName, $indicator['name']],
                    $this->retrieveAssessmentIndicatorMarks($assessmentId, $capabilityId, $indicatorId)
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

            // Output capability marks only if they are to be shown
            if ($capability['show']) {
                $capabilityMarks = $this->retrieveOverallCapabilityMarks($capability);
                $this->exportRow(
                    [$overallHeader, $capabilityName, $this->translator->translateSentence('({:rubricGroup} mark)')],
                    $capabilityMarks
                );
            }

            // Output indicator marks
            foreach ($capability['indicators'] as $indicatorId => $indicator) {
                $this->exportRow(
                    [$overallHeader, $capabilityName, $indicator['name']],
                    $this->retrieveOverallIndicatorMarks($indicator)
                );
            }
        }
    }


    /**
     * Retrieve the capability marks for this assessment out of student data.
     *
     * @param int $assessmentId
     * @param int $capabilityId
     * @return array
     */
    private function retrieveAssessmentCapabilityMarks(int $assessmentId, int $capabilityId): array
    {
        $capabilityMarks = [];
        foreach ($this->students as $studentId => $student) {
            if (empty($this->exclusions[$assessmentId][$studentId])) {
                $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] ?? '-';
            } else {
                $studentMark = '(excluded)';
            }
            $capabilityMarks[$studentId] = $studentMark;
        }
        return $capabilityMarks;
    }


    /**
     * Retrieve the indicator marks for this assessment out of student data.
     *
     * @param int $assessmentId
     * @param int $capabilityId
     * @param int $indicatorId
     * @return array
     */
    private function retrieveAssessmentIndicatorMarks(int $assessmentId, int $capabilityId, int $indicatorId): array
    {
        $indicatorMarks = [];
        foreach ($this->students as $studentId => $student) {
            if (empty($this->exclusions[$assessmentId][$studentId])) {
                $studentMark = $student['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] ?? '-';
            } else {
                $studentMark = '(excluded)';
            }
            $indicatorMarks[$studentId] = $studentMark;
        }
        return $indicatorMarks;
    }


    /**
     * Retrieve the capability marks out of overall data.
     *
     * @param array $capability
     * @return array
     */
    private function retrieveOverallCapabilityMarks(array $capability): array
    {
        $capabilityMarks = [];
        foreach ($this->students as $studentId => $student) {
            $capabilityMarks[$studentId] = $capability['marks'][$studentId]['humanReadable'] ?? '-';
        }
        return $capabilityMarks;
    }


    /**
     * Retrieve the indicator marks out of overall data.
     *
     * @param array $indicator
     * @return array
     */
    private function retrieveOverallIndicatorMarks(array $indicator): array
    {
        $indicatorMarks = [];
        foreach ($this->students as $studentId => $student) {
            $indicatorMarks[$studentId] = $indicator['marks'][$studentId]['humanReadable'] ?? '-';
        }
        return $indicatorMarks;
    }
}
