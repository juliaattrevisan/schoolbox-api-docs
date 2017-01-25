<?php
/**
 * Survey_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Survey_Question extends Evaluation_Question {

	/**
	 * Constructor
	 */
	public function __construct()
    {
		parent::__construct();
		$this->setSectionHandler(new Survey_Section());
	}

	/**
	 * Get all available question types.
	 *
	 * @return array|bool
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
