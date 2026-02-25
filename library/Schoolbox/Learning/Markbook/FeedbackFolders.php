<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;

/**
 * Finds a set of folder ids where the $students have a published feedback within a date range.
 */
class FeedbackFolders
{
    /**
     * @param IConnector $db
     */
    public function __construct(private IConnector $db)
    {
    }


    /**
     * Finds a set of folder ids where students had a published feedback between $from and $to (typically these will
     * correspond to the start of one academic period and the end of another: you get those separately outside of here)
     *
     * @param array $studentIds
     * @param \Alaress_Date_Format $from
     * @param \Alaress_Date_Format $to
     * @return null|array
     */
    public function findForStudents(
        array $studentIds,
        \Alaress_Date_Format $from,
        \Alaress_Date_Format $to
    ): ?array {
        /* Set up a predicate builder
         */
        $placeholders = Builder::getArrayPlaceholders($studentIds, 'studentIds');
        $params = Builder::getArrayParameters($studentIds, 'studentIds');

        /* Find all the assessments where $students had a published feedback in the date range specified
         */
        $statement = $this->db->prepareQuery("
            SELECT DISTINCT folder_id
            FROM v_most_recent_feedback vmrf
            INNER JOIN mv_assessment_relation mvar ON vmrf.assessment_id = mvar.assessment_id
            WHERE vmrf.student_id IN {$placeholders}
            AND vmrf.published_at BETWEEN :from AND :to
        ");

        $statement->execute(array_merge($params, [
            'from' => $from->startOfDay()->getDatabase(),
            'to' => $to->endOfDay()->getDatabase()
        ]));

        return $statement->fetchColumnArray('folder_id');
    }
}
