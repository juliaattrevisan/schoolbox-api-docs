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
        $query = "SELECT response_id
                  FROM {$this->_table}
                  WHERE attempt_id = '$attempt_id'
                  AND question_id = '$question_id'";

        return $this->db->getOne($query);
    }
}
?>
