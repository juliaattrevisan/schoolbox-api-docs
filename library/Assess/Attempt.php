<?php
/**
 * Assess_Attempt
 *
 * Assessment Attempts are managed through this class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Attempt extends Evaluation_Attempt {
    public $_table = 'assess_attempt';
    public $_index = 'attempt_id';

	/**
	 * Get all the attempts for a particular question
	 * This is used for horizontal marking
	 *
	 * @param $questionId
	 * @return array|bool
	 */
	public function getAllAttemptsForQuestion($questionId) {
		$query = "
			SELECT *
			FROM assess_question
			LEFT JOIN assess_response ON assess_question.question_id = assess_response.question_id
			LEFT JOIN assess_attempt ON assess_response.attempt_id = assess_attempt.attempt_id
			LEFT JOIN `user` ON assess_attempt.user_id = user.id
			WHERE assess_question.question_id = '{$questionId}'
			ORDER BY `user`.lastname, assess_attempt.finish DESC
		";

		return $this->db->getAll($query);
	}
}
