<?php
require_once '../include/model.php';

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
     * be marked automatically. The marking is performed by the answerType's
     * handler.
     * 
     * @param <type> $assess_id
     * @param <type> $attempt_id
     * @return <type>
     */
    function getAutomarkableResponses($assess_id, $attempt_id) {
        $query = "SELECT
                      assess_id, response_id,
                      assess_attempt.attempt_id,
                      {$this->_table}.question_id,
                      assess_luQuestionType.type_id,
                      automated_marking, marks, response, answer
                  FROM {$this->_table}
                  LEFT JOIN assess_attempt ON {$this->_table}.attempt_id = assess_attempt.attempt_id
                  LEFT JOIN assess_question ON assess_question.question_id = {$this->_table}.question_id
                  LEFT JOIN assess_luQuestionType ON assess_luQuestionType.type_id = assess_question.type_id
                  LEFT JOIN assess_answer ON assess_answer.question_id = assess_question.question_id
                  WHERE assess_attempt.attempt_id = '$attempt_id'
                  AND automated_marking = 'true'
                  AND automarked_already = 'false'";

        return $this->db->getAll($query);
    }

    /**
     * Remember which responses have been marked automatically, so we don't keep
     * refetching them every time the results page loads.
     * 
     * @param <type> $markedResponseIDs
     */
    function updatedAutomatedMarks($markedResponseIDs) {
        if (count($markedResponseIDs)) {
            $ids = implode(',', $markedResponseIDs);

            $query = "UPDATE {$this->_table}
                      SET automarked_already = 'true'
                      WHERE response_id IN ($ids)";

            $this->db->query($query);
        }
    }

    /**
     * Find the number of questions attempted in $attempt_id
     *
     * @param <type> $attempt_id
     * @return <type>
     */
    function numResponses($attempt_id) {
        $query = "SELECT COUNT(*) AS numQuestionsAttempted
                  FROM {$this->_table}
                  WHERE attempt_id = '$attempt_id'";

        return $this->db->getOne($query);
    }
}
?>
