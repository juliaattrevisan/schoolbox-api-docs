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
use Schoolbox\Learning\Assessment\AssessmentType;
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
     * Export the course markbook
     *
     * @param CourseMarkbook $courseMarkbook
     * @param string         $filename
     * @param bool           $showDrafts
     */
    public function export(CourseMarkbook $courseMarkbook, string $filename, bool $showDrafts): void
    {
        $this->initExport($filename);
        // grab assessments, and remove lesson plans
        $assessmentSpecs = $courseMarkbook->getCollapsedAssessments()->filter(static function (IAssessable $assessment) {
            return AssessmentType::TYPE_LESSON_PLAN !== $assessment->getFamilyName();
        });

        // Column headings
        $this->writeHeader($assessmentSpecs);

        // Course Average
        $courseAverages    = $courseMarkbook->getAssessmentSpecAverages();
        $courseAverageData = [];
        foreach ($assessmentSpecs as $assessmentSpec) {
            $mark = '';
            if (key_exists($assessmentSpec->getId(), $courseAverages)) {
                $normMark = $courseAverages[$assessmentSpec->getId()]['published'] ?? null;
                $isDraft  = false;
                if ($showDrafts) {
                    if (isset($courseAverages[$assessmentSpec->getId()]['any'])) {
                        $normMark = $courseAverages[$assessmentSpec->getId()]['any'];
                        $isDraft  = true;
                    }
                }
                if (isset($normMark)) {
                    $mark = sprintf("%6.2f %%", $normMark);
                    if ($isDraft) {
                        $mark .= self::DRAFT_SUFFIX;
                    }
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
                    $normMark = $classAverages[$assessmentImpl->getId()]['published'] ?? null;
                    $isDraft  = false;
                    if ($showDrafts) {
                        if (isset($classAverages[$assessmentImpl->getId()]['any'])) {
                            $normMark = $classAverages[$assessmentImpl->getId()]['any'];
                            $isDraft  = true;
                        }
                    }
                    if (isset($normMark)) {
                        $mark = sprintf("%6.2f %%", $normMark);
                        if ($isDraft) {
                            $mark .= self::DRAFT_SUFFIX;
                        }
                    }
                }
                $classAveragesData = $this->appendData($classAveragesData, $assessmentSpec, $mark);
            }
            $rowHeaders = [$class->getFolder()->getTitle(), '', '', ''];
            $rank       = [];
            if ($this->viewRank) {
                array_push($rowHeaders, '');
                $rank = $courseMarkbook->getRanking();
            }
            $this->exportRow($rowHeaders, $classAveragesData);
            // Per Students
            foreach (($courseMarkbook->getCourseImportStudents())[$class->getId()] as $student) {
                $studentAverageMark = '-';

                if (isset($studentAverageMarks[$student->getId()])) {
                    $hrMark  = $studentAverageMarks[$student->getId()]['published']['hr'] ?? null;
                    $isDraft = false;
                    if ($showDrafts) {
                        if (isset($studentAverageMarks[$student->getId()]['any']['hr'])) {
                            $hrMark  = $studentAverageMarks[$student->getId()]['any']['hr'];
                            $isDraft = true;
                        }
                    }
                    if (isset($studentAverageMarks[$student->getId()]['published']['hr'])) {
                        $studentAverageMark = $hrMark;
                        if ($isDraft) {
                            $studentAverageMark .= self::DRAFT_SUFFIX;
                        }
                    }
                }

                $studentData = [
                    $student->getExternalId(),
                    $student->getFirstname(),
                    $student->getLastname(),
                    $studentAverageMark
                ];
                if ($this->viewRank) {
                    $studentData[] = $rank[$student->getId()] ?? '';
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
                        $assessmentData = $this->appendStudentMarks(
                            $assessmentData,
                            $assessmentSpec,
                            $assessmentImpl,
                            $studentMarks[$class->getId()][$assessmentSpec->getId()][$student->getId()],
                            $student,
                            $showDrafts,
                        );
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
     * @param bool        $showDrafts
     * @return array
     */
    private function appendStudentMarks(
        array $row,
        IAssessable $assessment,
        IAssessable $assessmentImpl,
        array $currentMark,
        User $student,
        bool $showDrafts,
    ): array {
        $mark    = $currentMark['published'] ?? null;
        $isDraft = false;
        if ($showDrafts) {
            if (isset($currentMark['draft'])) {
                $mark    = $currentMark['draft'];
                $isDraft = true;
            }
        }
        if (!isset($mark)) {
            return [];
        }
        $suffix            = $isDraft ? self::DRAFT_SUFFIX : '';
        $humanReadableMark = $mark['hr'];
        if ($this->hasTotalMarkColumn($assessment)) {
            $row[] = $this->parseNumerator($humanReadableMark) . $suffix;
            $row[] = $this->parseDenominator($humanReadableMark) . $suffix;
        } else {
            $row[] = $humanReadableMark . $suffix;
        }
        if ($this->hasNormalizedMarkColumn($assessment)) {
            $row[] = $mark['norm'] . $suffix;
        }
        if ($this->hasCommentColumn($assessment)) {
            $row[] = $this->retrieveCommentField($assessmentImpl, $student) . $suffix;
        }
        return $row;
    }
}
