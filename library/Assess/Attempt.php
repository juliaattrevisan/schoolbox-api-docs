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
        $attempts = $this->retrieveLatestReviewableAttempts($assessId);
        if (empty($attempts)) {
            return [];
        }
        $attemptIds = implode(',', array_map([$this->db, 'quote'], array_column($attempts, 'attempt_id')));

        // Cross-reference them against questions
        $query = "
            SELECT *
            FROM assess_question aq
            INNER JOIN assess_response ar ON aq.question_id = ar.question_id
            WHERE aq.question_id = '{$this->db->quote($questionId)}'
            AND   ar.attempt_id IN ({$attemptIds})
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
        $attempts = $this->retrieveLatestReviewableAttempts($assessId);
        if (empty($attempts)) {
            return;
        }
        $attemptIds = implode(',', array_map([$this->db, 'quote'], array_column($attempts, 'attempt_id')));
        $now        = Alaress_Date_Format::now();

        // Review them right now
        $query = "
            UPDATE assess_attempt
            SET reviewed_at = '{$this->db->quote($now->getUsingFormat(Alaress_Date_Format::DATABASE_FORMAT))}'
            WHERE attempt_id IN ({$attemptIds})
        ";
        $this->db->query($query);
    }

    /**
     * Retrieves the latest attempts for each user that are still reviewable.
     * Reviewable attempts:
     * * aren't deleted
     * * aren't yet reviewed
     *
     * @param int $assessId
     * @return array
     */
    private function retrieveLatestReviewableAttempts($assessId)
    {
        $query = "
            SELECT *
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
                $results[$userId] = $row;
            }
        }
        return $results;
    }
}
