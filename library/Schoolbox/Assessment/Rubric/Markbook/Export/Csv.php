<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook\Export;

use DebugBar\DebugBarException;
use Schoolbox\L10n\RosettaStone as Translator;
use Schoolbox\Learning\Markbook\AbstractCSV;

/**
 * Output a set of assessments as a csv.
 */
class Csv extends AbstractCSV
{
    /**
     * These two nodes exist in $studentMarks to symmetrical segregate each kind of mark data.
     *
     * Note that there's also a Reasoning node if you needed to dig down into individual calculations here, but I doubt
     * anyone would ever want to do that in this particular usage.
     *
     * TODO Move this const to a more global space and share its usage: "published" and "draft" have hardcoded usages
     *  throughout the entire Rubric Markbook controller atm
     */
    private const string NODE_PUBLISHED = 'published';
    private const string NODE_DRAFT = 'draft';


    /**
     * A placeholder text used in the Overall Marks section in the assessment column.
     */
    private const string COLUMN_OVERALL = '(overall)';


    /**
     * This is a terminology for Rubric Capabilities ("groups", sadly, cos that's in the db still)
     *
     * @var string
     */
    private string $capabilityMarkColumn;


    /**
     * Which data struct node to access depending on the Show Drafts section.
     * This selection is constant throughout runtime; you can't swap them on a whim
     *
     * @var string
     */
    private string $node;


    /**
     * Constructor
     *
     * @param array $assessments A set of assessment data structs containing the cap/ind structure
     * @param array $exclusions A list of student ids that were excluded from assessments
     * @param array $studentMarks A list of students and the marks they got for each (assessment, capability, indicator) tuple
     * @param array $overallMarks Each student's weighted aggregated overall value for each capability and indicator
     * @param Translator $translator For localising terminology
     * @param bool $descriptorsOnly A bool that determines whether human readable marks show, or whether descriptor text shows
     * @param bool $showDrafts A bool that determines (if true) that we show draft marks; otherwise, shows published marks
     */
    public function __construct(
        private array $assessments,
        private array $exclusions,
        private array $studentMarks,
        private array $overallMarks,
        private Translator $translator,
        private bool $descriptorsOnly,
        bool $showDrafts
    ) {
        $this->capabilityMarkColumn = $this->translator->translateSentence('({:rubricGroup} mark)');

        /* Decide whether we're using Draft node or Published mark nodes.
         */
        $this->node = $this->nodeSelector($showDrafts);
    }


    /**
     * Export this markbook as a CSV. Note that ui settings are fulfilled here too.
     *
     * @param string $filename The filename for the CSV export.
     * @return void
     */
    public function export(string $filename): void
    {
        $this->initExport($filename);

        $this->exportHeader();

        /* Each grid in each assessment and all the students' marks for capabilities and indicators
         * This is the meat of the rubric markbook in a csv format.
         */
        foreach ($this->assessments as $assessmentId => $assessmentData) {
            foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricInstanceData) {
                $this->exportGrid(
                    (int)$assessmentId,
                    $assessmentData,
                    (int)$rubricInstanceId,
                    $rubricInstanceData,
                );
            }
        }

        /* The overall marks section for each student's progressive evolution for each capability and indicator.
         * This is the potatoes of the rubric markbook in csv format.
         */
        $this->exportOverallMarks();

        $this->closeExport();
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


    /**
     * Export a particular grid for a given $rubricInstanceData struct, with each student's individual output
     *
     * @param int $assessmentId
     * @param array $assessmentData
     * @param int $rubricInstanceId
     * @param array $rubricInstanceData
     * @return void
     */
    private function exportGrid(
        int $assessmentId,
        array $assessmentData,
        int $rubricInstanceId,
        array $rubricInstanceData,
    ): void {
        /* Send out each grid's capability/indicator combos for each student.
         */
        foreach ($rubricInstanceData['capabilities'] as $capabilityId => $capabilityData) {
            /* If the rubric grid used a Capability Mark Type (denoted by isMarkPerCapability) then we can show the
             * marks each student received for this capability. It's represented as an individual row where its
             * indicator title is "(capability mark)" so we hijack a column to do it. lol
             *
             * Excuse me while I ram in a whole new row for this circumstance before we continue onwards with our
             * usual programming
             */
            if ($rubricInstanceData['isMarkPerCapability']) {
                $marks = [];

                foreach ($this->studentMarks as $studentId => $studentData) {
                    $subnode = $studentData['gridData'][$assessmentId][$rubricInstanceId]['capabilities'][$capabilityId][$this->node] ?? null;

                    /* Show the text of the selected descriptor instead of its human-readable mark
                     */
                    if ($this->descriptorsOnly) {
                        $val = $subnode['selectedDescriptorText'] ?? null;
                    } else {
                        $val = $subnode['humanReadableMark'] ?? null;
                    }

                    /* Add this random char because of microsoft sinfulness
                     */
                    $marks[] = $this->mark($val);
                }

                /* The first arg to this method is the "headings" but it actually corresponds to the columns prior, that
                 * actually contain the assessment/rubric/cap/indicator titles that are the same for each student (kind
                 * of like a struct "prefix")
                 *
                 * Internally this simply array_merge()'s the two args anyway.
                 */
                $this->exportRow([
                    $assessmentData['name'],
                    $rubricInstanceData['title'],
                    $capabilityData['name'],
                    /* Use the terminology for "capability"
                     */
                    $this->capabilityMarkColumn,
                ], $marks);
            }

            /* Indicator marks for each student
             */
            foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                $marks = [];

                foreach ($this->studentMarks as $studentId => $studentData) {
                    $subnode = $studentData['gridData'][$assessmentId][$rubricInstanceId]['capabilities'][$capabilityId]['indicators'][$indicatorId][$this->node] ?? null;

                    /* Show the text of the selected descriptor instead of its human-readable mark
                     */
                    if ($this->descriptorsOnly) {
                        $val = $subnode['selectedDescriptorText'] ?? null;
                    } else {
                        $val = $subnode['humanReadableMark'] ?? null;
                    }

                    $marks[] = $this->mark($val);
                }

                $this->exportRow([
                    $assessmentData['name'],
                    $rubricInstanceData['title'],
                    $capabilityData['name'],
                    $indicatorData['name'],
                ], $marks);
            }
        }
    }


    /**
     * Each capability and indicator is listed uniquely here, along with each student's aggregated overall value for said
     * capabilities and indicators across all the assessments that used them.
     *
     * All in a nice csv format
     *
     * @return void
     */
    private function exportOverallMarks(): void
    {
        foreach ($this->overallMarks as $capabilityId => $capabilityData) {
            /* Squish in a row for Capability Mark data. For now we always show this value in Overall Marks.
             */
            $marks = [];

            foreach ($this->studentMarks as $studentId => $studentData) {
                $marks[] = $this->mark($this->overallMarks[$capabilityId]['students'][$studentId][$this->node]['humanReadableMark'] ?? null);
            }

            /* The first arg to this method is the "headings" but it actually corresponds to the columns prior, that
             * actually contain the assessment/rubric/cap/indicator titles that are the same for each student (kind
             * of like a struct "prefix")
             *
             * Internally this simply array_merge()'s the two args anyway.
             */
            $this->exportRow([
                /* A mock assessment name
                 */
                self::COLUMN_OVERALL,
                /* This column is not useful because we don't care about grids in this usage:
                 * slap in another placeholder text for now
                 */
                self::COLUMN_OVERALL,
                $capabilityData['name'],
                /* Use the terminology for "capability"
                 */
                $this->capabilityMarkColumn,
            ], $marks);

            /* Indicator marks for each student
             */
            foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                $marks = [];

                foreach ($this->studentMarks as $studentId => $studentData) {
                    $marks[] = $this->mark($this->overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$this->node]['humanReadableMark'] ?? null);
                }

                $this->exportRow([
                    /* A mock assessment name
                     */
                    self::COLUMN_OVERALL,
                    /* This column is not useful because we don't care about grids in this usage:
                     * slap in another placeholder text for now
                     */
                    self::COLUMN_OVERALL,
                    $capabilityData['name'],
                    $indicatorData['name'],
                ], $marks);
            }
        }
    }


    /**
     * Decides whether we're accessing the Published marks node or the Draft marks node based on the $showDrafts bool.
     * The $studentMarks data struct is built symmetrically so it's an easy toggle to use.
     *
     * @param bool $showDrafts
     * @return string
     */
    private function nodeSelector(bool $showDrafts): string
    {
        if ($showDrafts) {
            return self::NODE_DRAFT;
        }

        return self::NODE_PUBLISHED;
    }


    /**
     * Add this random char because of microsoft sinfulness
     *
     * @param string|null $val
     * @return string
     */
    private function mark(?string $val): string
    {
        if ('' === trim((string)$val)) {
            return '';
        }

        return self::EXCEL_PREFIX . trim($val);
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
