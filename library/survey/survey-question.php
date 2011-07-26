<?php
require_once('../library/assess/assess-question.php');

class SurveyQuestion extends AssessQuestion {

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
