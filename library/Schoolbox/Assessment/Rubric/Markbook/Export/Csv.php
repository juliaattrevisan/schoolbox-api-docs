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
     * Constructor
     *
     * @param array $assessments A set of assessment data structs containing the cap/ind structure
     * @param array $exclusions A list of student ids that were excluded from assessments
     * @param array $studentMarks A list of students and the marks they got for each (assessment, capability, indicator) tuple
     * @param array $overallMarks Each student's weighted aggregated overall value for each capability and indicator
     * @param Translator $translator For localising terminology
     * @param bool $descriptorsOnly A bool that determines whether human readable marks show, or whether descriptor text shows
     */
    public function __construct(
        private array $assessments,
        private array $exclusions,
        private array $studentMarks,
        private array $overallMarks,
        private Translator $translator,
        private bool $descriptorsOnly
    ) {
    }


    /**
     * Export this markbook as a CSV.
     *
     * @param string $filename   The filename for the CSV export.
     * @param bool   $showDrafts Should draft marks be shown in the export?
     * @return void
     */
    public function export(string $filename, bool $showDrafts): void
    {
        $this->initExport($filename);

        $this->exportHeader();
//        foreach ($this->assessments as $assessmentId => $assessment) {
//            $this->exportAssessment($assessmentId, $assessment, $showDrafts);
//        }
//        $this->exportOverallMarks($showDrafts);
//
        $this->closeExport();
    }


    /**
     * TEMP for development only; remove when finished
     * TODO remove this method
     *
     * @param string $filename
     */
    protected function initExport(string $filename): void
    {
        // set the output headers
        $this->fp = fopen("php://output", 'w');
    }


    /**
     * Export the header row for this CSV.
     *
     * This is a list of
     *     - Assessment (a localised terminology)
     *     - Rubric
     *     - Capability (a localised terminology)
     *     - Indicator (a localised terminology)
     *     - a set of student names derived from $studentMarks (a complete superset of guys)
     */
    private function exportHeader(): void
    {
        $this->exportRow(
            [
                $this->translator->translate('assessment', false, true),
                /* An assessment contains multiple grids; there isn't a terminology specifically for a rubric grid so
                 * I'll just use the generic words for now
                 */
                'Rubric',
                $this->translator->translate('rubricGroup', false, true),
                $this->translator->translate('rubricCriterion', false, true),
            ],
            array_map(function ($student) {
                return $this->exportStudentName($student);
            }, $this->studentMarks)
        );
    }


    /**
     * Helper for exporting a student's name.
     *
     * @param array $student
     * @return string
     */
    private function exportStudentName(array $student): string
    {
        /* Notably this doesn't care for a student's preferred name. There doesn't seem to be a particular convention
         * when it comes to the significance of Preferred Names if extant; though I would expect we'd lean toward formal
         * usage of Firstname for formal marky/reporty type stuff.
         *
         * TODO pump this into a User instance so you have access to the name formatting convenience methods, saving you
         *  a re-derivation of the exact same usage
         */
        return implode(', ', array_filter([
            $student['lastname'] ?? '',
            $student['firstname'] ?? ''
        ]));
    }


//
//
//    /**
//     * Export all rows for a single assessment for this CSV.
//     *
//     * @param int   $assessmentId
//     * @param array $assessment
//     * @param bool  $showDrafts
//     */
//    private function exportAssessment(int $assessmentId, array $assessment, bool $showDrafts): void
//    {
//        $assessmentName = $assessment['name'];
//        foreach ($assessment['capabilities'] as $capabilityId => $capability) {
//            $capabilityName = $capability['name'];
//
//            // Output capability marks only if this assessment uses them
//            if ($assessment['isMarkPerCapability']) {
//                $capabilityMarks = $this->retrieveAssessmentCapabilityMarks($assessmentId, $capabilityId, $showDrafts);
//                $this->exportRow(
//                    [$assessmentName, $capabilityName, $this->translator->translateSentence('({:rubricGroup} mark)')],
//                    $capabilityMarks
//                );
//            }
//
//            // Output indicator marks
//            foreach ($capability['indicators'] as $indicatorId => $indicator) {
//                $this->exportRow(
//                    [$assessmentName, $capabilityName, $indicator['name']],
//                    $this->retrieveAssessmentIndicatorMarks($assessmentId, $capabilityId, $indicatorId, $showDrafts)
//                );
//            }
//        }
//    }
//
//
//    /**
//     * Export overall mark rows for this CSV.
//     *
//     * @param bool $showDrafts
//     * @return void
//     */
//    private function exportOverallMarks(bool $showDrafts): void
//    {
//        foreach ($this->overallMarks as $capability) {
//            $overallHeader  = '(overall)';
//            $capabilityName = $capability['name'];
//
//            // Output capability marks only if they are to be shown
//            if ($capability['show']) {
//                $capabilityMarks = $this->retrieveOverallCapabilityMarks($capability, $showDrafts);
//                $this->exportRow(
//                    [$overallHeader, $capabilityName, $this->translator->translateSentence('({:rubricGroup} mark)')],
//                    $capabilityMarks
//                );
//            }
//
//            // Output indicator marks
//            foreach ($capability['indicators'] as $indicator) {
//                $this->exportRow(
//                    [$overallHeader, $capabilityName, $indicator['name']],
//                    $this->retrieveOverallIndicatorMarks($indicator, $showDrafts)
//                );
//            }
//        }
//    }
//
//
//    /**
//     * Retrieve the capability marks for this assessment out of student data.
//     *
//     * @param int  $assessmentId
//     * @param int  $capabilityId
//     * @param bool $showDrafts
//     * @return array
//     */
//    private function retrieveAssessmentCapabilityMarks(int $assessmentId, int $capabilityId, bool $showDrafts): array
//    {
//        $capabilityMarks = [];
//        foreach ($this->students as $studentId => $student) {
//            if (empty($this->exclusions[$assessmentId][$studentId])) {
//                $studentMark = $student['assessmentsPublished'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] ?? '';
//                if ($showDrafts) {
//                    if (isset($student['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'])) {
//                        $studentMark = $student['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] . self::DRAFT_SUFFIX;
//                    }
//                }
//                $studentMark = self::EXCEL_PREFIX . $studentMark;
//            } else {
//                $studentMark = '(excluded)';
//            }
//            $capabilityMarks[$studentId] = $studentMark;
//        }
//        return $capabilityMarks;
//    }
//
//
//    /**
//     * Retrieve the indicator marks for this assessment out of student data.
//     *
//     * @param int  $assessmentId
//     * @param int  $capabilityId
//     * @param int  $indicatorId
//     * @param bool $showDrafts
//     * @return array
//     */
//    private function retrieveAssessmentIndicatorMarks(
//        int $assessmentId,
//        int $capabilityId,
//        int $indicatorId,
//        bool $showDrafts,
//    ): array {
//        $indicatorMarks = [];
//        foreach ($this->students as $studentId => $student) {
//            if (empty($this->exclusions[$assessmentId][$studentId])) {
//                $result      = $student['assessmentsPublished'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId] ?? null;
//                $isDraftMark = false;
//                if ($showDrafts) {
//                    if (isset($student['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId])) {
//                        $result      = $student['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId];
//                        $isDraftMark = true;
//                    }
//                }
//                if (is_array($result)) {
//                    $studentMark = $this->descriptorsOnly && ($result['humanReadableMark'] ?? null)
//                        ? (self::EXCEL_PREFIX . $result['humanReadableMark'])
//                        : $result['selectedDescriptorText'];
//                    if ($isDraftMark) {
//                        $studentMark .= self::DRAFT_SUFFIX;
//                    }
//                } else {
//                    $studentMark = '';
//                }
//            } else {
//                $studentMark = '(excluded)';
//            }
//            $indicatorMarks[$studentId] = $studentMark;
//        }
//        return $indicatorMarks;
//    }
//
//
//    /**
//     * Retrieve the capability marks out of overall data.
//     *
//     * @param array $capability
//     * @param bool  $showDrafts
//     * @return array
//     */
//    private function retrieveOverallCapabilityMarks(array $capability, bool $showDrafts): array
//    {
//        $capabilityMarks = [];
//        foreach ($this->students as $studentId => $student) {
//            $capabilityMarks[$studentId] = $this->extractDraftOrPublishedValue(
//                $capability['marks'][$studentId] ?? [],
//                $showDrafts,
//                'humanReadable',
//                'latestHumanReadable',
//            );
//        }
//        return $capabilityMarks;
//    }
//
//
//    /**
//     * Retrieve the indicator marks out of overall data.
//     *
//     * @param array $indicator
//     * @param bool  $showDrafts
//     * @return array
//     */
//    private function retrieveOverallIndicatorMarks(array $indicator, bool $showDrafts): array
//    {
//        $indicatorMarks = [];
//        foreach ($this->students as $studentId => $student) {
//            $indicatorMarks[$studentId] = $this->extractDraftOrPublishedValue(
//                $indicator['marks'][$studentId] ?? [],
//                $showDrafts,
//                'humanReadable',
//                'latestHumanReadable',
//            );
//        }
//        return $indicatorMarks;
//    }
}
