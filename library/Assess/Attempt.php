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
			INNER JOIN (
				SELECT assess_attempt.attempt_id, user_id
				FROM  assess_attempt
				WHERE deleted_at IS NULL
				ORDER BY user_id, `start` DESC
			) el_boomerang ON assess_response.attempt_id = el_boomerang.attempt_id
			LEFT JOIN `user` ON el_boomerang.user_id = `user`.id
			WHERE assess_question.question_id = '{$questionId}'
			GROUP BY el_boomerang.user_id
			ORDER BY `user`.lastname
		";

		return $this->db->getAll($query);
	}
}
