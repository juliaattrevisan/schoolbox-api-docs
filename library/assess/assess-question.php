<?php
require_once '../include/model.php';

/**
 * Description of AssessQuestion class
 *
 * @author aetworowsky
 */
class AssessQuestion extends Model {
    public $_table = 'assess_question';
    public $_index = 'question_id';

    /**
     * Gets the last question entered into a particular section.
     * 
     * @param integer $section_id
     * @return array
     */
    public function getLast($section_id) {
        $query = "SELECT *
            FROM `$this->_table`
            WHERE `section_id` = $section_id
            ORDER BY `order`
            DESC LIMIT 1";

        return $this->db->getRow($query);
    }

    /**
     * For a new Question, find out what its sequence should be. If it's the
     * first Question for an Assess, return 0
     */
    function getNextSequence($section_id) {
        $query = "SELECT MAX(sequence)+1 AS num
                  FROM {$this->_table}
                  WHERE section_id = '$section_id'";

        $sequence = $this->db->getOne($query);

        if ($sequence) {
            return $sequence['num'];
        }

        return 1;
    }

    /**
     * Get all available question types.
     * 
     * @return <type>
     */
    function getQuestionTypes() {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }
}
?>
