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
}
?>
