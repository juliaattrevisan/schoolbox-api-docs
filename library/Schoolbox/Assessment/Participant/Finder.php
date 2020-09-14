<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant;

use Alaress\IDatabase;
use Alaress_Date_Format;

/**
 * Helper class for finding students participating in assessments in an
 * efficient manner.
 */
class Finder
{
    /**
     * @var IDatabase
     */
    private $database;


    /**
     * Constructor
     *
     * @param IDatabase $database
     */
    public function __construct(IDatabase $database)
    {
        $this->database = $database;
    }


    /**
     * Finds participations for a folder between a given start and end date.
     *
     * @param int                 $folderId
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @return array A set of custom participation records, grouped by
     *               assessment ID and then by student ID.
     *               Each result contains the keys:
     *               * excluded: is this student excluded from the assessment?
     *               * dueDate: the participation plan's due date
     */
    public function findParticipationsForFolder(
        int $folderId,
        Alaress_Date_Format $start,
        Alaress_Date_Format $end
    ): array {
        $customParticipationQuery = $this->database->query(
            '
                SELECT
                    vap.assessment_id,
                    vap.student_id,
                    vap.due_date,
                    vap.excluded_at
                FROM v_assessment_participation vap
                INNER JOIN assessment a ON vap.assessment_id = a.id
                WHERE folder_id = :folderId
                AND   (excluded_at IS NOT NULL OR (vap.due_date >= :startDate AND vap.due_date <= :endDate))
                AND   a.deleted_at IS NULL
            ',
            [
                'folderId'  => $folderId,
                'startDate' => $start->getDatabase(),
                'endDate'   => $end->getDatabase(),
            ]
        );

        // Group by assessment, then by student
        $customParticipations = [];
        foreach ($customParticipationQuery as $row) {
            $customParticipations[(int)$row['assessment_id']][(int)$row['student_id']] = [
                'excluded' => (bool)$row['excluded_at'],
                'dueDate'  => Alaress_Date_Format::newDatabaseDate($row['due_date']),
            ];
        }
        return $customParticipations;
    }


    /**
     * Finds all participations for this student between the start and end date.
     *
     * @param int                 $studentId
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @return array
     */
    public function findParticipationsForStudent(
        int $studentId,
        Alaress_Date_Format $start,
        Alaress_Date_Format $end
    ): array {
        $customParticipationQuery = $this->database->query(
            '
                SELECT
                    vap.assessment_id,
                    vap.student_id,
                    vap.folder_id,
                    vap.due_date,
                    vap.excluded_at,
                    f.name AS folderName,
                    a.title AS assessmentTitle
                FROM v_assessment_participation vap
                INNER JOIN assessment a              ON vap.assessment_id = a.id
                INNER JOIN v_assessment_relation var ON vap.assessment_id = var.assessment_id
                INNER JOIN folder f                  ON vap.folder_id     = f.id
                WHERE student_id = :studentId
                AND   a.deleted_at IS NULL
                AND (
                    excluded_at IS NOT NULL
                    OR
                    (vap.due_date >= :startDate AND vap.due_date <= :endDate)
                    OR
                    (a.due_date >= :startDate AND a.due_date <= :endDate)
                )
            ',
            [
                'studentId' => $studentId,
                'startDate' => $start->getDatabase(),
                'endDate'   => $end->getDatabase(),
            ]
        );

        // Group by assessment
        $customParticipations = [];
        foreach ($customParticipationQuery as $row) {
            $customParticipations[(int)$row['assessment_id']] = [
                'title'      => $row['assessmentTitle'],
                'folderName' => $row['folderName'],
                'folderId'   => (int)$row['folder_id'],
                'excluded'   => (bool)$row['excluded_at'],
                'dueDate'    => Alaress_Date_Format::newDatabaseDate($row['due_date']),
            ];
        }
        return $customParticipations;
    }
}
