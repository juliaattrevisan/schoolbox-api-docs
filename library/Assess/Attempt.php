<?php

use Alaress\Database\Predicate\Builder;

/**
 * Assess_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Attempt extends Evaluation_Attempt
{
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

    /**
     * Get all the attempts for a particular question
     * This is used for horizontal marking
     *
     * @param int $assessId
     * @param int $questionId
     * @return array|bool
     */
    public function getAllAttemptsForQuestion($assessId, $questionId)
    {
        // Retrieve all attempts
        $attempts = $this->retrieveLatestReviewableAttemptIds($assessId);
        if (empty($attempts)) {
            return [];
        }

        // Get everything for that question
        $placeholders = Builder::getArrayPlaceholders($attempts, 'attempt');
        $query = "
            SELECT *
            FROM assess_question aq
            INNER JOIN assess_response ar ON aq.question_id = ar.question_id
            INNER JOIN assess_attempt  aa ON ar.attempt_id  = aa.attempt_id
            LEFT  JOIN user            u  ON aa.user_id     = u.id
            WHERE aq.question_id = :questionId
            AND   ar.attempt_id IN {$placeholders}
            AND ar.deleted_at IS NULL
            ORDER BY u.lastname ASC, u.chosen_name ASC, u.id ASC
        ";
        $params = ['questionId' => $questionId] + Builder::getArrayParameters($attempts, 'attempt');

        return $this->db->query($query, $params)->fetchAll();
    }

    /**
     * Retrieves the IDs for each participant's most recent reviewable attempt.
     *
     * Reviewable attempts:
     * * aren't deleted
     * * aren't yet reviewed
     *
     * @param int $assessId
     * @return array
     */
    public function retrieveLatestReviewableAttemptIds($assessId)
    {
        $query = "
            SELECT user_id, attempt_id, reviewed_at
            FROM assess_attempt
            WHERE assess_id = ?
            AND   deleted_at IS NULL
            ORDER BY user_id, `start` DESC
        ";
        $results = [];
        $rows = $this->db->query($query, [$assessId])->fetchAll();
        if (!$rows) {
            return [];
        }
        foreach ($rows as $row) {
            $userId = $row['user_id'];
            if (!isset($results[$userId])) {
                $results[$userId] = $row['attempt_id'];
            }
        }
        return $results;
    }

    /**
     * Builds the contents of a `IN()` statement for the list of attempt IDs.
     *
     * @param int[] $attemptIds
     * @return string
     */
    private function buildReviewableAttemptsForQuery(array $attemptIds)
    {
        return implode(',', array_map([$this->db, 'smartQuote'], $attemptIds));
    }
}
