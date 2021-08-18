<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;

/**
 * Helper class for finding students participating in assessments in an
 * efficient manner.
 */
class Finder
{
    /**
     * @var IConnector
     */
    private $database;


    /**
     * Constructor
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }


    /**
     * Finds all participation records for a student in a set of folders,
     * between the window bounded by the start and end date.
     *
     * This respects custom assessment participation (CAP) as closely as
     * possible:
     * * Assess in window, no CAP:           use assess dates
     * * Assess in window, CAP in:           use CAP dates
     * * Assess in window, CAP out:          use assess dates
     * * Assess in window, CAP excluded:     use CAP dates, mark as excluded
     * * Assess out of window, no CAP:       ignore assess
     * * Assess out of window, CAP in:       use CAP dates
     * * Assess out of window, CAP out:      ignore assess
     * * Assess out of window, CAP excluded: ignore assess
     *
     * @param array               $folderIds IDs of folders to find within
     * @param int                 $studentId ID of student to find for
     * @param Alaress_Date_Format $start     Find only after this time
     * @param Alaress_Date_Format $end       Find only before this time
     * @return array * key:                 assessment ID
     *               * value['title']:      the assessment's title
     *               * value['folderName']: the assessment's folder's name
     *               * value['folderId']:   the assessment's folder's ID
     *               * value['excluded']:   is the student excluded?
     *               * value['dueDate']:    the assessment's due date for the
     *                                      student
     */
    public function findParticipationsForStudentInFolders(
        array $folderIds,
        int $studentId,
        Alaress_Date_Format $start,
        Alaress_Date_Format $end
    ): array {
        // Short-circuit if no folders
        if (empty($folderIds)) {
            return [];
        }


        $assessments        = [];
        $folderPrefix       = 'folderId';
        $folderParams       = Builder::getArrayParameters($folderIds, $folderPrefix);
        $folderPlaceholders = Builder::getArrayPlaceholders($folderIds, $folderPrefix);


        // Part 1: get all assessments in folder due within the window
        $assessmentQuery = $this->database->getQueryIterator(
            "
                SELECT
                    var.assessment_id,
                    var.folder_id,
                    a.due_date,
                    f.name AS folderName,
                    a.title AS assessmentTitle
                FROM v_assessment_relation var
                INNER JOIN assessment a              ON var.assessment_id = a.id
                INNER JOIN folder f                  ON var.folder_id     = f.id
                WHERE a.deleted_at IS NULL
                AND   var.folder_id IN {$folderPlaceholders}
                AND   a.due_date >= :startDate AND a.due_date <= :endDate
            ",
            $folderParams + [
                'startDate' => $start->getDatabase(),
                'endDate'   => $end->getDatabase(),
            ]
        );
        foreach ($assessmentQuery as $row) {
            $assessments[(int)$row['assessment_id']] = [
                'title'      => $row['assessmentTitle'],
                'folderName' => $row['folderName'],
                'folderId'   => (int)$row['folder_id'],
                'excluded'   => false,
                'dueDate'    => Alaress_Date_Format::newDatabaseDate($row['due_date']),
            ];
        }


        // Part 2: get CAP for student for all assessments retrieved in part 1,
        // regardless of due date
        // Override exclusion setting, and filter out any assessments outside
        // the window
        if (!empty($assessments)) {
            $assessmentIds          = array_keys($assessments);
            $assessmentPrefix       = 'assessment';
            $assessmentParams       = Builder::getArrayParameters($assessmentIds, $assessmentPrefix);
            $assessmentPlaceholders = Builder::getArrayPlaceholders($assessmentIds, $assessmentPrefix);
            $query                  = $this->database->getQueryIterator(
                "
                    SELECT
                        a.id                              AS assessment_id,
                        COALESCE(pp.due_date, a.due_date) AS due_date,
                        p.excluded_at
                    FROM assessment a
                    INNER JOIN participant_plan pp ON a.id  = pp.assessment_id AND pp.deleted_at IS NULL
                    INNER JOIN participant      p  ON pp.id = p.participant_plan_id
                    WHERE p.user_id = :studentId
                    AND   a.id IN {$assessmentPlaceholders}
                ",
                $assessmentParams + [
                    'studentId' => $studentId,
                ]
            );
            foreach ($query as $row) {
                $assessmentId = (int)$row['assessment_id'];
                $dueDate      = Alaress_Date_Format::newDatabaseDate($row['due_date']);
                if (!$dueDate->between($start, $end)) {
                    unset($assessments[$assessmentId]);
                } else {
                    $assessments[$assessmentId]['excluded'] = (bool)$row['excluded_at'];
                    $assessments[$assessmentId]['dueDate']  = Alaress_Date_Format::newDatabaseDate($row['due_date']);
                }
            }
        }


        // Part 3: get CAP for student for folder, within the window
        // Override any assessment details
        $customParticipationQuery = $this->database->getQueryIterator(
            "
                SELECT
                    vap.assessment_id,
                    vap.student_id,
                    vap.folder_id,
                    vap.due_date,
                    vap.excluded_at,
                    f.name AS folderName,
                    a.title AS assessmentTitle
                FROM v_assessment_participation vap
                INNER JOIN assessment a ON vap.assessment_id = a.id
                INNER JOIN folder     f ON vap.folder_id     = f.id
                WHERE vap.student_id = :studentId
                AND   a.deleted_at IS NULL
                AND   vap.folder_id IN {$folderPlaceholders}
                AND   vap.due_date >= :startDate
                AND   vap.due_date <= :endDate
            ",
            $folderParams + [
                'studentId' => $studentId,
                'startDate' => $start->getDatabase(),
                'endDate'   => $end->getDatabase(),
            ]
        );
        foreach ($customParticipationQuery as $row) {
            $assessments[(int)$row['assessment_id']] = [
                'title'      => $row['assessmentTitle'],
                'folderName' => $row['folderName'],
                'folderId'   => (int)$row['folder_id'],
                'excluded'   => (bool)$row['excluded_at'],
                'dueDate'    => Alaress_Date_Format::newDatabaseDate($row['due_date']),
            ];
        }


        return $assessments;
    }
}
