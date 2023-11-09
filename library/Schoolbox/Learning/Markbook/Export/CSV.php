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
use Schoolbox\Learning\Markbook\AbstractCSV;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\User;

/**
 * Export a markbook in CSV format
 */
class CSV extends AbstractCSV
{
    /**
     * Export this markbook as a CSV.
     *
     * @param Markbook $markbook   The markbook to export.
     * @param string   $filename   The filename for the CSV export.
     * @param bool     $showDrafts Should draft marks be shown in the export?
     * @return void
     */
    public function export(Markbook $markbook, string $filename, bool $showDrafts): void
    {
        $this->initExport($filename);

        $students          = $this->getSortedStudents($markbook);
        $participants      = $this->getSortedParticipants($markbook);
        $sortedAssessments = $this->getSortedAssessments($markbook);

        $this->writeHeader($sortedAssessments);
        $this->writeWeightedMean($sortedAssessments, $markbook, $showDrafts);
        $this->writeStudents($sortedAssessments, $markbook, $students, $participants, $showDrafts);

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
     * @return void
     */
    private function writeWeightedMean(GenericCollection $sortedAssessments, Markbook $markbook, bool $showDrafts): void
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
            $averageRow = $this->addAverage($averageRow, $markbook, $assessment, $showDrafts);
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
     */
    private function writeStudents(
        GenericCollection $sortedAssessments,
        Markbook $markbook,
        GenericCollection $students,
        GenericCollection $participants,
        bool $showDrafts,
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
                $studentRow = $this->addParticipant($studentRow, $participants, $assessment, $userId, $showDrafts);
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
     * @return string[]
     * @return array
     */
    private function addAverage(array $row, Markbook $markbook, IAssessable $assessment, bool $showDrafts): array
    {
        $stats = $markbook->getAssessmentStatistics()[$assessment->getId()];
        $row[] = $this->extractDraftOrPublishedValue($stats, $showDrafts, 'mean', 'draftMean');
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
     * Append fields into participant row for specified assessment
     *
     * @param string[]                                              $row
     * @param GenericCollection|GenericCollection[]|Participant[][] $participants Indexed by assessment.id, then user.id
     * @param IAssessable                                           $assessment
     * @param int                                                   $userId
     * @param bool                                                  $showDrafts
     * @return string[]
     */
    private function addParticipant(
        array $row,
        GenericCollection $participants,
        IAssessable $assessment,
        int $userId,
        bool $showDrafts,
    ): array {
        if (
            $participants[$assessment->getId()] instanceof Collection
            && $participants[$assessment->getId()][$userId] instanceof Participant
            && !$participants[$assessment->getId()][$userId]->isExcluded()
        ) {
            $participant = $participants[$assessment->getId()][$userId];
            $feedback    = $participant->getAuthoritativeFeedback(!$showDrafts);
            if ($feedback) {
                $suffix           = $feedback->isDraft() ? self::DRAFT_SUFFIX : '';
                $humanReadableMark = $feedback->getHumanReadableMark();
                if ($this->hasTotalMarkColumn($assessment)) {
                    $row[] = $this->parseNumerator($humanReadableMark) . $suffix;
                    $row[] = $this->parseDenominator($humanReadableMark) . $suffix;
                } else {
                    $row[] = $humanReadableMark . $suffix;
                }
                if ($this->hasNormalizedMarkColumn($assessment)) {
                    $row[] = $feedback->getNormalisedMark() . $suffix;
                }
                if ($this->hasCommentColumn($assessment)) {
                    $row[] = $feedback->getComment() . $suffix;
                }
                return $row;
            }
        }

        // Fallback if nothing to display
        $row[] = '';
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
}
