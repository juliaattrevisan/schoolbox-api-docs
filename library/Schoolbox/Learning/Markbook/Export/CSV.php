<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook\Export;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Markbook\AbstractCSV;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\User;

/**
 * Export a markbook in CSV format
 */
class CSV extends AbstractCSV
{
    /**
     * This should be used in situations where there is no data to
     * export, but a blank string would be inappropriate or misleading.
     */
    private const string NOT_APPLICABLE_PLACEHOLDER = '-';

    /**
     * Export this markbook as a CSV.
     *
     * @param Markbook $markbook   The markbook to export.
     * @param string   $filename   The filename for the CSV export.
     * @param bool     $showDrafts Should draft marks be shown in the export?
     * @param bool     $showDisplayMarks Should display (student) marks be shown in the export instead of instructor marks?
     * @return void
     */
    public function export(Markbook $markbook, string $filename, bool $showDrafts, bool $showDisplayMarks): void
    {
        $this->initExport($filename);

        $students          = $this->getSortedStudents($markbook);
        $participants      = $this->getSortedParticipants($markbook);
        $sortedAssessments = $this->getSortedAssessments($markbook);

        $this->writeHeader($sortedAssessments, $showDisplayMarks);
        $this->writeWeightedMean($sortedAssessments, $markbook, $showDrafts, $showDisplayMarks);
        $this->writeStudents($sortedAssessments, $markbook, $students, $participants, $showDrafts, $showDisplayMarks);

        $this->closeExport();
    }


    /**
     * Get all students who have participated in any of the assessments in the markbook, indexed by user.id
     *
     * @param Markbook $markbook
     * @return GenericCollection|User[] $students
     */
    private function getSortedStudents(Markbook $markbook): GenericCollection
    {
        return new GenericCollection(User::class, $markbook->getStudents()->indexBy('id')->getAll());
    }


    /**
     * Get participants indexed by assessment.id, then user.id
     *
     * @param Markbook $markbook
     * @return GenericCollection|GenericCollection[]|Participant[][] $participants
     */
    private function getSortedParticipants(Markbook $markbook)
    {
        $participants = new GenericCollection(GenericCollection::class);
        foreach ($markbook->getAssessments() as $assessment) {
            if (!$participants->keyExists($assessment->getId())) {
                $participants[$assessment->getId()] = new GenericCollection(Participant::class);
            }
            foreach ($assessment->getAllParticipants() as $participant) {
                $participants[$assessment->getId()][$participant->getUser()->getId()] = $participant;
            }
        }
        return $participants;
    }


    /**
     * Get assessments sorted as displayed in markbook, indexed by assessment.id
     *
     * @param Markbook $markbook
     * @return GenericCollection|IAssessable[]
     */
    private function getSortedAssessments(Markbook $markbook)
    {
        $sortedAssessments = new GenericCollection(IAssessable::class);
        foreach ($markbook->getAssessments() as $assessment) {
            $sortedAssessments[$assessment->getId()] = $assessment;
        }
        return $sortedAssessments;
    }


    /**
     * Write CSV row for weighted mean per assessment
     *
     * @param GenericCollection|IAssessable[] $sortedAssessments
     * @param Markbook                        $markbook
     * @param bool                            $showDrafts
     * @param bool                            $showDisplayMarks
     * @return void
     */
    private function writeWeightedMean(GenericCollection $sortedAssessments, Markbook $markbook, bool $showDrafts, bool $showDisplayMarks): void
    {
        // Average row
        $averageRowHeaders = [
            'Class Average',
            '',
            '',
            $markbook->getWeightedMean(),
        ];
        if ($this->viewRank) {
            array_push($averageRowHeaders, '');
        }
        $averageRow        = [];
        foreach ($sortedAssessments as $assessment) {
            $averageRow = $this->addAverage($averageRow, $markbook, $assessment, $showDrafts, $showDisplayMarks);
        }
        $this->exportRow($averageRowHeaders, $averageRow);
    }


    /**
     * For each student, write a row of their human readable mark, normalised mark, and comment if it exists
     *
     * @param GenericCollection|IAssessable[] $sortedAssessments
     * @param Markbook                        $markbook
     * @param GenericCollection               $students
     * @param GenericCollection               $participants
     * @param bool                            $showDrafts
     * @param bool                            $showDisplayMarks
     */
    private function writeStudents(
        GenericCollection $sortedAssessments,
        Markbook $markbook,
        GenericCollection $students,
        GenericCollection $participants,
        bool $showDrafts,
        bool $showDisplayMarks
    ): void {
        $rank = [];
        if ($this->viewRank) {
            $rank = $markbook->getRanking();
        }
        // Student rows
        foreach ($students as $userId => $student) {
            $studentRowHeaders = [
                $student->getExternalId(),
                $student->getFirstname(),
                $student->getLastname(),
                $this->extractDraftOrPublishedValue(
                    $markbook->getStudentStatistics()[$student->getId()],
                    $showDrafts,
                    'weightedMean',
                    'draftWeightedMean',
                ),
            ];
            if ($this->viewRank) {
                $studentRowHeaders[] = $rank[$student->getId()] ?? '';
            }

            $studentRow = [];
            foreach ($sortedAssessments as $assessment) {
                $studentRow = $this->addParticipant($studentRow, $participants, $assessment, $userId, $showDrafts, $showDisplayMarks);
            }
            $this->exportRow($studentRowHeaders, $studentRow);
        }
    }


    /**
     * Append fields into weighted-mean row for specified assessment
     *
     * @param string[]    $row
     * @param Markbook    $markbook
     * @param IAssessable $assessment
     * @param bool        $showDrafts If true: display a draft if it's there,
     *                                otherwise show only the non-draft mean
     * @param bool        $showDisplayMarks
     * @return string[]
     * @return array
     */
    private function addAverage(array $row, Markbook $markbook, IAssessable $assessment, bool $showDrafts, bool $showDisplayMarks): array
    {
        $stats = $markbook->getAssessmentStatistics()[$assessment->getId()];

        // The 'mean' obtained here is the mean of the normalised marks (see Markbook->collateAssessmentStatistics()).
        $row[] = $this->extractDraftOrPublishedValue($stats, $showDrafts, 'mean', 'draftMean');

        if ($this->hasTotalMarkColumn($assessment, $showDisplayMarks)) {
            // Not needed because the average of the normalised marks is more useful and already provided (see above).
            $row[] = '';
        }
        if ($this->hasNormalizedMarkColumn($assessment, $showDisplayMarks)) {
            // Not needed because the provided value is already normalised (see above).
            $row[] = '';
        }
        if ($this->hasCommentColumn($assessment)) {
            $row[] = '';
        }
        return $row;
    }


    /**
     * Append fields into participant row for specified assessment
     *
     * @param string[]                                              $row
     * @param GenericCollection|GenericCollection[]|Participant[][] $participants Indexed by assessment.id, then user.id
     * @param IAssessable                                           $assessment
     * @param int                                                   $userId
     * @param bool                                                  $showDrafts
     * @param bool                                                  $showDisplayMarks
     * @return string[]
     */
    private function addParticipant(
        array $row,
        GenericCollection $participants,
        IAssessable $assessment,
        int $userId,
        bool $showDrafts,
        bool $showDisplayMarks
    ): array {
        if (
            $participants[$assessment->getId()] instanceof Collection
            && $participants[$assessment->getId()][$userId] instanceof Participant
            && !$participants[$assessment->getId()][$userId]->isExcluded()
        ) {
            $participant = $participants[$assessment->getId()][$userId];
            $suffix = null;
            $humanReadableMark = null;
            $normalisedMark = null;
            $comment = null;
            $dataFound = false;

            if ($assessment instanceof Project) {
                // For projects, there is no feedback, so get data from other places.
                $suffix = $assessment->isReviewComplete($participant) ? '' : ($showDrafts ? self::DRAFT_SUFFIX : '');
                $humanReadableMark = $showDisplayMarks
                    ? $participant->getHumanReadableMark(!$showDrafts)
                    : $participant->getHumanReadableInstructorMark(!$showDrafts);
                $normalisedMark = $participant->getNormalisedMark(!$showDrafts);
                $comment = '';
                $dataFound = true;
            } elseif ($assessment instanceof \Schoolbox\Learning\Assessment\Task\Model) {
                /* Tasks can be
                 *    - unmarked, or
                 *    - self-assessed (response provided by student) or
                 *    - teacher-assessed (feedback provided by teacher)
                 */
                $feedback = $participant->getAuthoritativeFeedback(!$showDrafts);

                if ($feedback) {
                    /* First find out if the teacher has provided feedback
                     */
                    $suffix = $feedback->isDraft() ? self::DRAFT_SUFFIX : '';
                    $humanReadableMark = $feedback->getHumanReadableMark(); // Tasks only have one mark type, so always use humanReadableMark
                    $normalisedMark = $feedback->getNormalisedMark();
                    $comment = $feedback->getComment();
                    $dataFound = true;
                } else {
                    /* If this task allowed self-assessment, dig into Responses in the absence of teacher feedbacks.
                     * Look for student-given feedback instead, because they might have self-assessed.
                     * Somewhat confusingly, these are made as Responses from the student
                     */
                    if ($assessment->getIsSelfAssessable()) {
                        /** @var \Schoolbox\Assessment\Course\Task\Completion|null $response */
                        $response = $participant->findMostRecentSubmittedResponse();

                        if ($response) {
                            /* Tasks have no draft states: once you set their state, that's it
                             */
                            $suffix = '';
                            $humanReadableMark = \Schoolbox\Assessment\Course\Task\Completion::STATUS_MAP[$response->getCompletionStatus()] ?? '';
                            $normalisedMark = null;
                            $comment = null;
                            $dataFound = true;
                        }
                    }
                }
            } else {
                // For non-projects, all the data is in the feedback.
                $feedback = $participant->getAuthoritativeFeedback(!$showDrafts);

                if ($feedback) {
                    $suffix = $feedback->isDraft() ? self::DRAFT_SUFFIX : '';
                    // Only due works have instructor mark schemes, all the others use humanReadableMark
                    $humanReadableMark = ($showDisplayMarks || !($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model))
                        ? $feedback->getHumanReadableMark()
                        : $feedback->getHumanReadableInstructorMark();
                    $normalisedMark = $feedback->getNormalisedMark();
                    $comment = $feedback->getComment();
                    $dataFound = true;
                }
            }

            if ($dataFound) {
                if ($this->hasTotalMarkColumn($assessment, $showDisplayMarks)) {
                    if (empty($humanReadableMark)) {
                        $row[] = '';
                        $row[] = '';
                    } else {
                        $numerator = $this->parseNumerator($humanReadableMark);
                        $denominator = $this->parseDenominator($humanReadableMark);
                        if ($numerator !== null && $denominator !== null) {
                            $row[] = $numerator . $suffix;
                            $row[] = $denominator . $suffix;
                        } else {
                            // Although this assessment has a total mark column, the human-readable mark is not
                            // fractional.  This will be because the mark type of the assessment has changed after the
                            // mark was given.  Do not attempt to convert the mark to a fraction.  Just export the
                            // human-readable mark in the numerator column and an N/A placeholder in the denominator column.
                            $row[] = $humanReadableMark;
                            $row[] = self::NOT_APPLICABLE_PLACEHOLDER;
                        }
                    }
                } else {
                    $row[] = $humanReadableMark == '' ? '' : $humanReadableMark . $suffix;
                }
                if ($this->hasNormalizedMarkColumn($assessment, $showDisplayMarks)) {
                    $row[] = $normalisedMark === null ? '' : round($normalisedMark, 2) . $suffix;
                }
                if ($this->hasCommentColumn($assessment)) {
                    $row[] = $comment == '' ? '' : $comment . $suffix;
                }
                return $row;
            }
        }

        // Fallback if nothing to display
        $row[] = '';
        if ($this->hasTotalMarkColumn($assessment, $showDisplayMarks)) {
            $row[] = '';
        }
        if ($this->hasNormalizedMarkColumn($assessment, $showDisplayMarks)) {
            $row[] = '';
        }
        if ($this->hasCommentColumn($assessment)) {
            $row[] = '';
        }

        return $row;
    }
}
