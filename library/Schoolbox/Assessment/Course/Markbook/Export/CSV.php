<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook\Export;

use Schoolbox\Assessment\Course\Markbook\CourseMarkbook;
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Markbook\Export\CSV as ClassMarkbookCSV;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\User;

/**
 * Exports a course markbook in CSV format
 */
class CSV
{
    /** File */
    private $fp;

    /**
     * Exports
     * @param CourseMarkbook $courseMarkbook
     * @param string         $filename
     */
    public function export(CourseMarkbook $courseMarkbook, string $filename): void
    {
        $this->initExport($filename);
        $assessmentSpecs = $courseMarkbook->getCollapsedAssessments();

        $csvHelper = new ClassMarkbookCSV();


        // Column headings
        $headings         = [
            'Student Id',
            'Student First Name',
            'Student Last Name',
            'Student Weighted Average',
        ];
        $assessmentTitles = [];
        foreach ($assessmentSpecs as $assessmentSpec) {
            $assessmentTitles = $csvHelper->addHeaders($assessmentTitles, $assessmentSpec);
        }
        $this->exportRow($headings, $assessmentTitles);


        // Course Average
        $courseAverages    = $courseMarkbook->getAssessmentSpecAverages();
        $courseAverageData = [];
        foreach ($assessmentSpecs as $assessmentSpec) {
            $mark = '';
            if (key_exists($assessmentSpec->getId(), $courseAverages)) {
                $mark = sprintf("%6.2f", $courseAverages[$assessmentSpec->getId()]['published']);
            }
            $courseAverageData = $this->appendData($courseAverageData, $assessmentSpec, $mark);
        }
        $this->exportRow(['Course Average', '', '', ''], $courseAverageData);


        $classes               = $courseMarkbook->getCourseImpls();
        $studentMarks          = $courseMarkbook->getStudentMarks();
        $classAverages         = $courseMarkbook->getAssessmentImplAverages();
        $studentTaskProgresses = $courseMarkbook->getTaskProgress();

        foreach ($classes as $class) {
            $classAveragesData = [];

            /** @var GenericCollection $classAssessments */
            $classAssessments    = $courseMarkbook->getCourseImportAssessments()->offsetGet($class->getId());
            $studentAverageMarks = ($courseMarkbook->getStudentAverageMarks())[$class->getId()];

            // Class Average
            foreach ($assessmentSpecs as $assessmentSpec) {
                $mark           = '';
                $assessmentImpl = $classAssessments->offsetGet($assessmentSpec->getId());
                if ($assessmentImpl && key_exists($assessmentImpl->getId(), $classAverages)) {
                    $mark = sprintf("%6.2f", $classAverages[$assessmentImpl->getId()]['published']);
                }
                $classAveragesData = $this->appendData($classAveragesData, $assessmentSpec, $mark);
            }
            $this->exportRow([$class->getFolder()->getTitle(), '', '', ''], $classAveragesData);

            // Per Students
            foreach (($courseMarkbook->getCourseImportStudents())[$class->getId()] as $student) {
                $studentData = [
                    $student->getId(),
                    $student->getFirstname(),
                    $student->getLastname(),
                    isset($studentAverageMarks[$student->getId()]) ? $studentAverageMarks[$student->getId()]['published']['hr'] : '-',
                ];
                // Per Assessment
                $assessmentData = [];
                foreach ($assessmentSpecs as $assessmentSpec) {
                    $assessmentImpl = $classAssessments->offsetGet($assessmentSpec->getId());
                    if (!$assessmentImpl) {
                        $assessmentData = $this->appendData($assessmentData, $assessmentSpec, '');
                        continue;
                    }
                    /** @var IAssessable $assessmentImpl */
                    $participant = $assessmentImpl->getParticipantIfExtant($student);
                    if (!$participant || $participant->isExcluded()) {
                        $assessmentData = $this->appendData($assessmentData, $assessmentSpec, '');
                        continue;
                    }

                    if (isset($studentMarks[$class->getId()][$assessmentSpec->getId()][$student->getId()])) {
                        // Due work and Quiz
                        $assessmentData = $this->appendStudentMarks($assessmentData, $assessmentSpec, $assessmentImpl, $studentMarks[$class->getId()][$assessmentSpec->getId()][$student->getId()], $student);
                    } elseif (isset($studentTaskProgresses[$class->getId()][$assessmentSpec->getId()][$student->getId()])) {
                        // Task
                        switch ($studentTaskProgresses[$class->getId()][$assessmentSpec->getId()][$student->getId()]) {
                            case TaskCompletion::STATUS_TEACHER_ASSESSED_COMPLETE:
                            case TaskCompletion::STATUS_SELF_ASSESSED_COMPLETE:
                                $mark = Completion::HUMAN_READABLE_COMPLETE;
                                break;
                            case TaskCompletion::STATUS_INCOMPLETE:
                                $mark = Completion::HUMAN_READABLE_INCOMPLETE;
                                break;
                            default:
                                $mark = '';
                        }
                        $assessmentData = $this->appendData($assessmentData, $assessmentSpec, $mark);
                    } else {
                        $assessmentData = $this->appendData($assessmentData, $assessmentSpec, '');
                    }
                }
                $this->exportRow($studentData, $assessmentData);
            }
        }

        $this->closeExport();
    }


    /**
     * Appends mark along with extra fields
     *
     * @param array       $row
     * @param IAssessable $assessment
     * @param string      $mark
     * @return array
     */
    private function appendData(array $row, IAssessable $assessment, string $mark): array
    {
        $csvHelper = new ClassMarkbookCSV();
        $row[]     = $mark;
        if ($csvHelper->hasTotalMarkColumn($assessment)) {
            $row[] = '';
        }
        if ($csvHelper->hasNormalizedMarkColumn($assessment)) {
            $row[] = '';
        }
        if ($csvHelper->hasCommentColumn($assessment)) {
            $row[] = '';
        }
        return $row;
    }

    /**
     * Appends mark for Due work and Quiz
     *
     * @param array       $row
     * @param IAssessable $assessment
     * @param IAssessable $assessmentImpl
     * @param array       $currentMark
     * @param User        $student
     * @return array
     */
    private function appendStudentMarks(array $row, IAssessable $assessment, IAssessable $assessmentImpl, array $currentMark, User $student)
    {
        $csvHelper = new ClassMarkbookCSV();

        $humanReadableMark = $currentMark['published']['hr'];
        if ($csvHelper->hasTotalMarkColumn($assessment)) {
            $row[] = $csvHelper->parseNumerator($humanReadableMark);
            $row[] = $csvHelper->parseDenominator($humanReadableMark);
        } else {
            $row[] = $humanReadableMark;
        }
        if ($csvHelper->hasNormalizedMarkColumn($assessment)) {
            $row[] = $currentMark['published']['norm'];
        }
        if ($csvHelper->hasCommentColumn($assessment)) {
            $row[] = $csvHelper->retrieveCommentField($assessmentImpl, $student);
        }
        return $row;
    }

    /**
     * Opens file to export
     * @param string $filename
     */
    private function initExport(string $filename): void
    {
        // set the output headers
        header('Content-type: text/csv');
        header('Content-disposition: attachment; filename="' . $filename . '');

        $this->fp = fopen("php://output", 'r');
    }

    /**
     * Exports a row
     * @param $rowHeadings
     * @param $rowData
     */
    private function exportRow(array $rowHeadings, array $rowData): void
    {
        fputcsv($this->fp, array_merge($rowHeadings, $rowData));
    }

    /**
     * Closes exported file
     */
    private function closeExport(): void
    {
        fclose($this->fp);
    }
}
