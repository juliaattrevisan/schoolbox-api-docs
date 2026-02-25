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
 *
 * It uses a correlation between views and materialised views, not joining them explicitly, to save on a potential
 * performance penalty that can occur when joining a complex view(s))
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

        /* Find all the assessments where $students had a published feedback in th date range specified
         */
        $statement = $this->db->prepareQuery("
            SELECT DISTINCT assessment_id
            FROM v_most_recent_feedback
            WHERE student_id IN {$placeholders}
            AND published_at BETWEEN :from AND :to
        ");

        $statement->execute(array_merge($params, [
            'from' => $from->startOfDay()->getDatabase(),
            'to' => $to->endOfDay()->getDatabase()
        ]));

        /* Collect all the assessment ids where $students had a published feedback
         */
        $assessmentIds = $statement->fetchColumnArray('assessment_id');

        /* No feedbacks found for the set of $students that were published between the date range specified
         */
        if (!count($assessmentIds)) {
            return null;
        }

        /* Set up another predicate builder
         */
        $placeholders = Builder::getArrayPlaceholders($assessmentIds, 'assessmentIds');
        $params = Builder::getArrayParameters($assessmentIds, 'assessmentIds');

        /* MAYBE you can get away with joining mv_assessment_relation with v_most_recent_feedback......maybe
         * But for now, let's just assume the worst, and correlate the data sets instead.
         *
         * TODO Experiment with a join between mvar and vmrf on actual data
         */
        $statement = $this->db->prepareQuery("
            SELECT DISTINCT folder_id
            FROM mv_assessment_relation
            WHERE assessment_id IN {$placeholders}
        ");

        $statement->execute($params);

        return $statement->fetchColumnArray('folder_id');
    }
}
