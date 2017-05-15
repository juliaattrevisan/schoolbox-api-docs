<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook\Export;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Markbook\IExporter;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\User;

/**
 * Export a markbook in CSV format
 */
class CSV implements IExporter
{

    /**
     * @inheritdoc
     */
    public function export(Markbook $markbook, $filename)
    {
        $this->sendHeaders($filename);

        $students          = $this->getSortedStudents($markbook);
        $participants      = $this->getSortedParticipants($markbook);
        $sortedAssessments = $this->getSortedAssessments($markbook);

        $out = fopen('php://output', 'wb');

        $this->writeHeader($out, $sortedAssessments);
        $this->writeWeightedMean($out, $sortedAssessments, $markbook);
        $this->writeStudents($out, $sortedAssessments, $markbook, $students, $participants);

        fclose($out);
    }


    /**
     * Send HTTP headers to instruct client to treat as CSV file download
     *
     * @param string $filename
     */
    private function sendHeaders($filename)
    {
        header('Content-Disposition: attachment; filename="' . $filename . '.csv";');
        header('Content-Type: application/csv; charset=UTF-8');
    }


    /**
     * Get all students who have participated in any of the assessments in the markbook, indexed by user.id
     *
     * @param Markbook $markbook
     * @return GenericCollection|User[] $students
     */
    private function getSortedStudents(Markbook $markbook)
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
        foreach ($markbook->getAssessmentsWithProjectContents() as $assessment) {
            $sortedAssessments[$assessment->getId()] = $assessment;
        }
        return $sortedAssessments;
    }


    /**
     * Write CSV column headers
     *
     * @param resource                        $out
     * @param GenericCollection|IAssessable[] $sortedAssessments
     */
    private function writeHeader($out, GenericCollection $sortedAssessments)
    {
        // Column headings
        $row = [
            'Student Id',
            'Student First Name',
            'Student Last Name',
            'Student Average',
        ];
        foreach ($sortedAssessments as $assessment) {
            $this->addHeaders($row, $assessment);
        }
        fputcsv($out, $row);
    }


    /**
     * Write CSV row for weighted mean per assessment
     *
     * @param resource                        $out
     * @param GenericCollection|IAssessable[] $sortedAssessments
     * @param Markbook                        $markbook
     */
    private function writeWeightedMean($out, GenericCollection $sortedAssessments, Markbook $markbook)
    {
        // Average row
        $averageRow = [
            'Markbook Average',
            '',
            '',
            $markbook->getWeightedMean(),
        ];
        foreach ($sortedAssessments as $assessment) {
            $this->addAverage($averageRow, $markbook, $assessment);
        }
        fputcsv($out, $averageRow);
    }


    /**
     * For each student, write a row of their human readable mark, normalised mark, and comment if it exists
     *
     * @param resource                        $out
     * @param GenericCollection|IAssessable[] $sortedAssessments
     * @param Markbook                        $markbook
     * @param GenericCollection               $students
     * @param GenericCollection               $participants
     */
    private function writeStudents($out, GenericCollection $sortedAssessments, Markbook $markbook, GenericCollection $students, GenericCollection $participants)
    {
        // Student rows
        foreach ($students as $userId => $student) {
            $studentRow = [
                $student->getExternalId(),
                $student->getFirstname(),
                $student->getLastname(),
                $markbook->getStudentStatistics()[$student->getId()]['weightedMean'],
            ];

            foreach ($sortedAssessments as $assessmentId => $assessment) {
                $this->addParticipant($studentRow, $participants, $assessmentId, $userId);
            }
            fputcsv($out, $studentRow);
        }
    }


    /**
     * Append fields into header row for specified assessment
     *
     * @param string[]    $row
     * @param IAssessable $assessment
     * @return string[]
     */
    private function addHeaders(array &$row, IAssessable $assessment)
    {
        $title = "{$assessment->getTitle()} \r\n( {$assessment->getTypeName()})";
        $row[] = "$title";
        $row[] = "$title (Normalized)";
        $row[] = "$title (Comment)";
        return $row;
    }


    /**
     * Append fields into weighted-mean row for specified assessment
     *
     * @param string[]    $row
     * @param Markbook    $markbook
     * @param IAssessable $assessment
     * @return string[]
     */
    private function addAverage(array &$row, Markbook $markbook, IAssessable $assessment)
    {
        $row[] = $markbook->getAssessmentStatistics()[$assessment->getId()]['mean'];
        $row[] = '';
        $row[] = '';
        return $row;
    }


    /**
     * Append fields into participant row for specified assessment
     *
     * @param string[]                                              $row
     * @param GenericCollection|GenericCollection[]|Participant[][] $participants Indexed by assessment.id, then user.id
     * @param int                                                   $assessmentId
     * @param int                                                   $userId
     * @return string[]
     */
    private function addParticipant(array &$row, GenericCollection $participants, $assessmentId, $userId)
    {
        if ($participants[$assessmentId]->keyExists($userId)) {
            $mostRecentFeedback = $participants[$assessmentId][$userId]->findMostRecentFeedback();

            $row[] = $participants[$assessmentId][$userId]->getPublishedHumanReadableMark();
            $row[] = $participants[$assessmentId][$userId]->getPublishedNormalisedMark();
            $row[] = $mostRecentFeedback instanceof IFeedback ? $mostRecentFeedback->getComment() : '';
        } else {
            $row[] = '';
            $row[] = '';
            $row[] = '';
        }
        return $row;
    }

}
