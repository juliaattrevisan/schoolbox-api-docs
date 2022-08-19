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
use Schoolbox\Learning\Markbook\AbstractCSV;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\User;

/**
 * Exports a course markbook in CSV format
 */
class CSV extends AbstractCSV
{
    /**
     * Exports
     * @param CourseMarkbook $courseMarkbook
     * @param string         $filename
     */
    public function export(CourseMarkbook $courseMarkbook, string $filename): void
    {
        $this->initExport($filename);
        $assessmentSpecs = $courseMarkbook->getCollapsedAssessments();

        // Column headings
        $this->writeHeader($assessmentSpecs);

        // Course Average
        $courseAverages    = $courseMarkbook->getAssessmentSpecAverages();
        $courseAverageData = [];
        foreach ($assessmentSpecs as $assessmentSpec) {
            $mark = '';
            if (key_exists($assessmentSpec->getId(), $courseAverages)) {
                /* There isn't a published feedback
                 * Nothing to show; exit early
                 */
                if (isset($courseAverages[$assessmentSpec->getId()]['published'])) {
                    $mark = sprintf("%6.2f %%", $courseAverages[$assessmentSpec->getId()]['published']);
                }
            }
            $courseAverageData = $this->appendData($courseAverageData, $assessmentSpec, $mark);
        }
        $this->exportRow(['Course Average', '', '', ''], $courseAverageData);

        // Class data for each class
        $classes               = $courseMarkbook->getCourseImpls();
        $studentMarks          = $courseMarkbook->getStudentMarks();
        $classAverages         = $courseMarkbook->getAssessmentImplAverages();
        $studentTaskProgresses = $courseMarkbook->getTaskProgress();

        foreach ($classes as $class) {
            $classAveragesData = [];

            /** @var GenericCollection $classAssessments */
            $classAssessments    = $courseMarkbook->getCourseImportAssessments()->offsetGet($class->getId());
            $studentAverageMarksData = $courseMarkbook->getStudentAverageMarks();

            /* Class id not found in the average marks data; default to empty
             */
            $studentAverageMarks = $studentAverageMarksData[$class->getId()] ?? [];

            // Class Average
            foreach ($assessmentSpecs as $assessmentSpec) {
                $mark           = '';
                $assessmentImpl = $classAssessments->offsetGet($assessmentSpec->getId());
                if ($assessmentImpl && key_exists($assessmentImpl->getId(), $classAverages)) {
                    /* There isn't a published feedback
                     * Nothing to show; exit early
                     */
                    if (isset($classAverages[$assessmentImpl->getId()]['published'])) {
                        $mark = sprintf("%6.2f %%", $classAverages[$assessmentImpl->getId()]['published']);
                    }
                }
                $classAveragesData = $this->appendData($classAveragesData, $assessmentSpec, $mark);
            }
            $rowHeaders = [$class->getFolder()->getTitle(), '', '', ''];
            $rankByStudentId = [];
            if ($this->viewRank) {
                array_push($rowHeaders, '');
                $rankByStudentId = $courseMarkbook->getRankByStudentId();
            }
            $this->exportRow($rowHeaders, $classAveragesData);
            // Per Students
            foreach (($courseMarkbook->getCourseImportStudents())[$class->getId()] as $student) {
                $studentAverageMark = '-';

                if (isset($studentAverageMarks[$student->getId()])) {
                    /* There isn't a published feedback
                     * Nothing to show; exit early
                     */
                    if (isset($studentAverageMarks[$student->getId()]['published']['hr'])) {
                        $studentAverageMark = $studentAverageMarks[$student->getId()]['published']['hr'];
                    }
                }

                $studentData = [
                    $student->getExternalId(),
                    $student->getFirstname(),
                    $student->getLastname(),
                    $studentAverageMark
                ];
                if ($this->viewRank) {
                    $studentData[] = $rankByStudentId[$student->getId()] ?? '';
                }
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
        $row[] = $mark;
        if ($this->hasTotalMarkColumn($assessment)) {
            $row[] = '';
        }
        if ($this->hasNormalizedMarkColumn($assessment)) {
            $row[] = '';
        }
        if ($this->hasCommentColumn($assessment)) {
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
    private function appendStudentMarks(array $row, IAssessable $assessment, IAssessable $assessmentImpl, array $currentMark, User $student): array
    {
        /* There isn't a published feedback
         * Nothing to show; exit early
         */
        if (!isset($currentMark['published'])) {
            return [];
        }

        $humanReadableMark = $currentMark['published']['hr'];
        if ($this->hasTotalMarkColumn($assessment)) {
            $row[] = $this->parseNumerator($humanReadableMark);
            $row[] = $this->parseDenominator($humanReadableMark);
        } else {
            $row[] = $humanReadableMark;
        }
        if ($this->hasNormalizedMarkColumn($assessment)) {
            $row[] = $currentMark['published']['norm'];
        }
        if ($this->hasCommentColumn($assessment)) {
            $row[] = $this->retrieveCommentField($assessmentImpl, $student);
        }
        return $row;
    }
}
