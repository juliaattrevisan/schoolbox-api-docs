<?php
require_once '../include/model.php';

/**
 * Description of AssessResponse
 *
 * @author aetworowsky
 */
class AssessResponse extends Model {
    public $_table = 'assess_response';
    public $_index = 'response_id';

    /**
     * Finds out whether a response exists for a particular question during an
     * attempt.
     * 
     * @param <type> $attempt_id
     * @param <type> $question_id
     * @return <type>
     */
    function isResponseExists($attempt_id, $question_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE attempt_id = '$attempt_id'
                  AND question_id = '$question_id'";

        return $this->db->getRow($query);
    }

    /**
     * Gets all the responses to a particular assessment and attempt which can
     * be marked automatically.
     * 
     * @param <type> $assess_id
     * @param <type> $attempt_id
     * @return <type>
     */
    function getAutomarkableResponses($assess_id, $attempt_id) {
        $query = "SELECT
                      user_id, assess_id, response_id, assess_attempt.attempt_id, section_id, assess_response.question_id,
                      automated_marking, marks, response, answer
                  FROM {$this->_table}
                  LEFT JOIN assess_attempt ON {$this->_table}.attempt_id = assess_attempt.attempt_id
                  LEFT JOIN assess_question ON assess_question.question_id = {$this->_table}.question_id
                  LEFT JOIN assess_luQuestionType ON assess_luQuestionType.type_id = assess_question.type_id
                  LEFT JOIN assess_answer ON assess_answer.question_id = assess_question.question_id
                  WHERE assess_attempt.attempt_id = '$attempt_id'
                  AND automated_marking = 'true'";

        return $this->db->getAll($query);
    }
}
?>
