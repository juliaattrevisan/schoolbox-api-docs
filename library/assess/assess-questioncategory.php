<?php

class AssessQuestionCategory extends DeprecatedModel {
    public $_table = 'assess_question_category';
    public $_index = 'assess_question_category_id';

	public function getList() {
		$query = "SELECT *
				  FROM {$this->_table}
				  ORDER BY name";

		return $this->db->getAll($query);
	}
}
?>
