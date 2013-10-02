<?php
/**
 * Assess_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Question extends Evaluation_Question {
    public $_table = 'assess_question';
    public $_index = 'question_id';

	public function __construct() {
		parent::__construct();

		$this->sectionHandler = new Assess_Section();
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
                  OR availability = 'assess'
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }

	/**
	 * Get the next question in the sequence
	 *
	 * @param $assessId
	 * @param $questionSequence
	 * @return array|bool
	 */
	public function getNextQuestion($assessId, $questionSequence) {
		$query = "
			SELECT *
			FROM assess_section
			LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
			WHERE assess_question.sequence > $questionSequence
			AND assess_section.assess_id = '{$assessId}'
			ORDER BY assess_section.sequence, assess_question.sequence
			LIMIT 1
		";

		return $this->db->getRow($query);
	}

	/**
	 * Get the previous question in the sequence
	 *
	 * @param $assessId
	 * @param $questionSequence
	 * @return array|bool
	 */
	public function getPreviousQuestion($assessId, $questionSequence) {
		$query = "
			SELECT *
			FROM assess_section
			LEFT JOIN assess_question ON assess_section.section_id = assess_question.section_id
			WHERE assess_question.sequence < '{$questionSequence}'
			AND assess_section.assess_id = '{$assessId}'
			ORDER BY assess_section.sequence, assess_question.sequence DESC
			LIMIT 1
		";

		return $this->db->getRow($query);
	}
}
