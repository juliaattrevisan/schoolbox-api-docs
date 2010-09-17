<?php
require_once '../include/model.php';

/**
 * Description of AssessAttempt
 *
 * @author aetworowsky
 */
class AssessAttempt extends Model {
    var $_table = 'assess_attempt';
    var $_index = 'attempt_id';

    /**
     * Mark an attempt as having been reviewed.
     *
     * @param <type> $attempt_id
     */
    function reviewed($attempt_id) {
        $data = array(
            'attempt_id' => $attempt_id,
            'reviewed_at' => 'NOW()'
        );

        parent::update($data);
    }

    /**
     * The number of attempts that have been reviewed for an assess
     * 
     * @param <type> $assess_id
     * @return <type>
     */
    function numReviewed($assess_id) {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND reviewed_at IS NOT NULL";

        return $this->db->getOne($query);
    }

    /**
     * The number of attempts made for an assess. Group them by user id to
     * cater for multiple attempts.
     * 
     * @param <type> $assess_id
     * @return <type>
     */
    function numAttempted($assess_id) {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  GROUP BY user_id";

        return $this->db->getOne($query);
    }

    /**
     * Creates an attempt and marks the start time
     *
     * @param integer $user_id
     * @return mixed
     */
    function start($user_id, $assess_id) {
        if (!is_numeric($user_id) || !is_numeric($assess_id)) {
            return false;
        }

        $attempt_id = parent::create(array(
            'assess_id' => $assess_id,
            'user_id' => $user_id,
            'start' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
            return false;
        }
    }

    /**
     * Marks an attempt as being finished
     *
     * @param integer $attempt_id
     * @return mixed
     */
    public function finish($attempt_id) {
        if(!is_numeric($attempt_id)) {
            return false;
        }

        $attempt_id = $this->update(array(
            'assess_id' => $attempt_id,
            'finish' => date('Y-m-d H:i:s')
        ));

        if (is_numeric($attempt_id)) {
            return $attempt_id;
        }
        else {
            return false;
        }
    }

    /**
     * Check if the current user has already completed this assessment.
     *
     * @param <type> $assess_id
     */
    function isComplete($assess_id) {
        $query = "SELECT COUNT(*) AS completed
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$_SESSION[id]'
                  AND finish != '0000-00-00 00:00:00'";

        return $this->db->getOne($query);
    }

    /**
     * Get a list of attempts that have been made, along with their authors
     */
    function getList($assess_id) {
        $query = "SELECT
                      assess_attempt.attempt_id,
                      assess_attempt.user_id, firstname, lastname,
                      assess_attempt.start, assess_attempt.finish,
                      ROUND(assess_attempt.finish - assess_attempt.start) AS timeTaken,
                      SUM(assess_response.mark) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN `user` ON assess_attempt.user_id = user.id
                  LEFT JOIN assess ON assess_attempt.assess_id = assess.assess_id
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess.assess_id = '$assess_id'
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getAll($query);
    }

    /**
     * Find the total marks available for an attempt (noting that an attempt
     * can have varying total available marks, depending on randomness of
     * questions which themselves have different available marks from other
     * candidates which could be selected)
     */
    function getTotalMarks($assess_id, $attempt_id) {
        $query = "SELECT SUM(marks) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  LEFT JOIN assess_question ON assess_response.question_id = assess_question.question_id
                  WHERE assess_attempt.assess_id = '$assess_id'
                  AND assess_attempt.attempt_id = '$attempt_id'";

        return $this->db->getOne($query);
    }

    /**
     * Out of multiple attempts at the same assess, get a user's most recent one
     * 
     * @param <type> $assess_id
     * @param <type> $user_id
     * @return <type>
     */
    function getLatestForUser($assess_id, $user_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE assess_id = '$assess_id'
                  AND user_id = '$user_id'
                  ORDER BY start DESC";

        return $this->db->getRow($query);
    }

    function getMarksForAttempt($attempt_id) {
        $query = "SELECT
                      SUM(assess_response.mark) AS totalMarks
                  FROM assess_attempt
                  LEFT JOIN assess_response ON assess_attempt.attempt_id = assess_response.attempt_id
                  WHERE assess_attempt.attempt_id = '$attempt_id'
                  GROUP BY assess_attempt.attempt_id";

        return $this->db->getOne($query);
    }

}
?>
