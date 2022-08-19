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
     * @inheritdoc
     */
    public function export(Markbook $markbook, string $filename): void
    {
        $this->initExport($filename);

        $students          = $this->getSortedStudents($markbook);
        $participants      = $this->getSortedParticipants($markbook);
        $sortedAssessments = $this->getSortedAssessments($markbook);

        $this->writeHeader($sortedAssessments);
        $this->writeWeightedMean($sortedAssessments, $markbook);
        $this->writeStudents($sortedAssessments, $markbook, $students, $participants);

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
     */
    private function writeWeightedMean(GenericCollection $sortedAssessments, Markbook $markbook): void
    {
        // Average row
        $averageRowHeaders = [
            'Markbook Average',
            '',
            '',
            $markbook->getWeightedMean(),
        ];
        if ($this->viewRank) {
            array_push($averageRowHeaders, '');
        }
        $averageRow        = [];
        foreach ($sortedAssessments as $assessment) {
            $averageRow = $this->addAverage($averageRow, $markbook, $assessment);
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
     */
    private function writeStudents(GenericCollection $sortedAssessments, Markbook $markbook, GenericCollection $students, GenericCollection $participants): void
    {
        $rankByStudentId = [];
        if ($this->viewRank) {
            $rankByStudentId = $markbook->getRankByStudentId();
        }
        // Student rows
        foreach ($students as $userId => $student) {
            $studentRowHeaders = [
                $student->getExternalId(),
                $student->getFirstname(),
                $student->getLastname(),
                $markbook->getStudentStatistics()[$student->getId()]['weightedMean'],
            ];
            if ($this->viewRank) {
                $studentRowHeaders[] = $rankByStudentId[$student->getId()] ?? '';
            }

            $studentRow = [];
            foreach ($sortedAssessments as $assessmentId => $assessment) {
                $studentRow = $this->addParticipant($studentRow, $participants, $assessment, $userId);
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
     * @return string[]
     */
    private function addAverage(array $row, Markbook $markbook, IAssessable $assessment): array
    {
        $row[] = $markbook->getAssessmentStatistics()[$assessment->getId()]['mean'];
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
     * @return string[]
     */
    private function addParticipant(array $row, GenericCollection $participants, IAssessable $assessment, $userId): array
    {
        if (
            $participants[$assessment->getId()] instanceof Collection
            && $participants[$assessment->getId()][$userId] instanceof Participant
            && !$participants[$assessment->getId()][$userId]->isExcluded()
        ) {
            $participant = $participants[$assessment->getId()][$userId];

            $humanReadableMark = $participant->getHumanReadableMark(true);

            if ($this->hasTotalMarkColumn($assessment)) {
                $row[] = $this->parseNumerator($humanReadableMark);
                $row[] = $this->parseDenominator($humanReadableMark);
            } else {
                $row[] = $humanReadableMark;
            }
            if ($this->hasNormalizedMarkColumn($assessment)) {
                $row[] = $participant->getNormalisedMark(true);
            }
            if ($this->hasCommentColumn($assessment)) {
                $row[] = $this->retrieveCommentField($assessment, $participant->getUser());
            }
        } else {
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
        }
        return $row;
    }
}
