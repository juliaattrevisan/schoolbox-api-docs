<?php
/**
 * Assess_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Attempt extends Evaluation_Attempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

    /**
     * Get all the attempts for a particular question
     * This is used for horizontal marking
     *
     * @param $assessId
     * @param $questionId
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
        $query = "
            SELECT *
            FROM assess_question aq
            INNER JOIN assess_response ar ON aq.question_id = ar.question_id
            INNER JOIN assess_attempt  aa ON ar.attempt_id  = aa.attempt_id
            LEFT  JOIN user            u  ON aa.user_id     = u.id
            WHERE aq.question_id = '{$this->db->quote($questionId)}'
            AND   ar.attempt_id IN ({$this->buildReviewableAttemptsForQuery($attempts)})
        ";

        return $this->db->getAll($query);
    }

    /**
     * Update the reviewed_at for all the latest attempts for a particular assess :D
     * How do you like them apples
     *
     * @param $assessId
     */
    public function finaliseAllLatestAttempts($assessId)
    {
        // Retrieve all attempts
        $attempts = $this->retrieveLatestReviewableAttemptIds($assessId);
        if (empty($attempts)) {
            return;
        }
        $now = Alaress_Date_Format::now();

        // Review them right now
        $query = "
            UPDATE assess_attempt
            SET reviewed_at = '{$this->db->quote($now->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT))}'
            WHERE attempt_id IN ({$this->buildReviewableAttemptsForQuery($attempts)})
        ";
        $this->db->query($query);
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
    private function retrieveLatestReviewableAttemptIds($assessId)
    {
        $query = "
            SELECT user_id, attempt_id
            FROM assess_attempt
            WHERE assess_id = '{$this->db->quote($assessId)}'
            AND   deleted_at IS NULL
            AND   reviewed_at IS NULL
            ORDER BY user_id, `start` DESC
        ";
        $results = [];
        foreach ($this->db->getAll($query) as $row) {
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
        return implode(',', array_map([$this->db, 'quote'], $attemptIds));
    }
}
