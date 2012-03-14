<?php
/**
 * Evaluation_Prototype_Attempt_Container
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Attempt_Container extends DeprecatedModel {
	private $evaluation_id = null;
	private $userAttempts = 0;

	/**
	 * Constructor
	 */
	public function __construct($evaluation_id) {
		parent::__construct();

		$this->setEvaluationId($evaluation_id);
	}

	/**
	 * Get number of attempts by $user_id on this Evaluation
	 *
	 * @param  $user_id
	 * @return
	 */
	public function getNumAttempts($user_id) {
        $query = "SELECT COUNT(*)
                  FROM assess
                  WHERE assess_id = '{$this->getEvaluationId()}'
                  AND user_id = '$user_id'";

        return $this->db->getOne($query);
	}

	/**
	 * Out of multiple attempts at the same assess, get a user's most recent one
	 *
	 * @param  $user_id
	 * @return
	 */
    public function getLatestForUser($user_id) {
        $query = "SELECT *
                  FROM assess
                  WHERE assess_id = '{$this->getEvaluationId()}'
                  AND user_id = '$user_id'
				  AND deleted_at IS NULL
                  ORDER BY start DESC";

        return $this->db->getRow($query);
    }

	/* Setters/Getters
	 */

	/**
	 * Set evaluation id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setEvaluationId($val) {
		$this->evaluation_id = $val;
	}

	/**
	 * Get evaluation id
	 *
	 * @return null
	 */
	private function getEvaluationId() {
		return $this->evaluation_id;
	}

	/**
	 * Set number of attempts by a particular user
	 *
	 * @param  $val
	 * @return void
	 */
	public function setUserAttempts($val) {
		$this->userAttempts = $val;
	}

	/**
	 * Get number of attempts by a particular user
	 *
	 * @param  $val
	 * @return void
	 */
	public function getUserAttempts() {
		return $this->userAttempts;
	}
}
