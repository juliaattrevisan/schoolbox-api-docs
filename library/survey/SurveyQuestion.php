<?php
/**
 * AssessQuestion class. All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
require_once($_SERVER['DOCUMENT_ROOT'] . '/../library/evaluation/EvaluationQuestion.php');

class SurveyQuestion extends EvaluationQuestion {
    protected $_table = 'assess_question';
    protected $_index = 'question_id';

    /**
     * Get all available question types.
     *
     * @return <type>
     */
    public function getAllQuestionTypes() {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  WHERE availability = 'all'
                  OR availability = 'survey'
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }
}
?>
