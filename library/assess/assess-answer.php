<?php
require_once '../include/model.php';

/**
 * Description of AssessQuestion class
 *
 * @author aetworowsky
 */
class AssessAnswer extends Model {
    public $_table = 'assess_answer';
    public $_index = 'answer_id';

    /**
     * Gets the Answer record for a particular Question.
     *
     * @param <type> $question_id
     * @return <type>
     */
    function getForQuestion($question_id) {
        $query = "SELECT *
                 FROM {$this->_table}
                 WHERE question_id = '$question_id'
                 ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Load answer template
     */
    function loadTemplate($type) {
        return file_get_contents("../templates/core/assess/answer/$type/create.html");
    }
}
?>