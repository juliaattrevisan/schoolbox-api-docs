<?php

abstract class EvaluationQuestionCategory extends DeprecatedModel {
    public $_table = 'assess_question_category';
    public $_index = 'assess_question_category_id';

	/**
	 * Get a list of QuestionCategories to chooze from
	 *
	 * @return array|bool
	 */
	public function getList() {
		$query = "SELECT *
				  FROM {$this->_table}
				  ORDER BY name";

		return $this->db->getAll($query);
	}
}
?>
