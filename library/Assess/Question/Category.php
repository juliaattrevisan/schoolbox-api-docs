<?php
/**
 * Assess_Question_Category
 *
 * Manages categories within the QuestionBank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Assess_Question_Category extends Evaluation_Question_Category {
    public $_table = 'assess_question_category';
    public $_index = 'assess_question_category_id';

    	/**
	 * Get a list of QuestionCategories to chooze from
	 *
	 * @return array|bool
	 */
	public function getList() {
		$query = "SELECT *
				  FROM {$this->_table} WHERE availability = 'assess'
				  ORDER BY name";

		return $this->db->getAll($query);
	}
}
