<?php
/**
 * Evaluation_Prototype_Permission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Evaluation_Prototype_Permission extends DeprecatedModel {
	const UNLIMITED_DURATION = 1;

	private $user_id = null;
	private $evaluation_id = null;

	/**
	 * Constructor
	 *
	 * @param  $evaluation_id
	 */
	public function __construct($evaluation_id, $latestAttempt) {
		parent::__construct();

		$this->setEvaluationId($evaluation_id);
		$this->setLatestAttempt($latestAttempt);

		$this->loadEvaluation();
	}

	private function setLatestAttempt($val) {
		$this->latestAttempt = $val;
	}

	private function getLatestAttempt() {
		return $this->latestAttempt;
	}

	/**
	 * Load Evaluation
	 *
	 * @return void
	 */
	public function loadEvaluation() {
		$query = "SELECT *
					FROM assess
					WHERE assess_id = '{$this->getEvaluationId()}'
					AND deleted_at IS NULL";

		$this->setEvaluation($this->db->getRow($query));
	}

	/**
     * Determines whether the current user can take a test by checking which
     * categories the current user belongs to.
	 *
	 * @param  $evaluation_id
	 * @return bool
	 */
    public function canDoAssessment($user_id) {
		$evaluation = $this->getEvaluation();

        $groupHandler = new Group();
        $categoryList = $groupHandler->involvedInCategoryList($user_id);

		/* See if you're in the Category first
		 */
		if (is_array($categoryList)) {
			foreach ($categoryList as $key => $value) {
				if ($value['id'] == $evaluation['category_id']) {
					return true;
				}
			}
		}

        return false;
    }

	/**
	 * Whether or not this Evaluation has been deleted
	 * @return bool
	 */
	public function isDeleted() {
		$evaluation = $this->getEvaluation();

		if ($evaluation === false) {
			return true;
		}

		return false;
	}

	/**
	 * Whether or not you're allowed to make numerous Attempts
	 *
	 * @return void
	 */
	public function allowResit() {
        $query = "SELECT allow_resit
                  FROM assess
                  WHERE assess_id = '{$this->getEvaluationId()}'";

        return $this->db->getOne($query);
	}

	/**
	 * Whether or not you've completed this Evaluation in the past
	 *
	 * @return void
	 */
	public function isComplete() {
        $query = "SELECT COUNT(*) AS completed
                  FROM assess
                  WHERE assess_id = '{$this->getEvaluationId()}'
                  AND user_id = '{$this->getUserId()}'
                  AND finish IS NOT NULL
				  AND deleted_at IS NULL";

        return $this->db->getRow($query);
	}

	/**
	 * Whether or not the Evaluation's end date was set in the past
	 *
	 * @return void
	 */
	public function isExpired() {
		$evaluation = $this->getEvaluation();

		if ($evaluation) {
			if (($evaluation['duration'] != self::UNLIMITED_DURATION) && (strtotime($evaluation['finish']) <= time())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether or not your attempt has reached the maximum duration
	 * set for this timed Evaluation
	 *
	 * @return bool
	 */
	public function isDurationElapsed() {
		$evaluation = $this->getEvaluation();

		$attempt = $this->getLatestAttempt();
		$attemptStartTime = strtotime($attempt['start']);
		$attemptTimeRemaining = $attemptStartTime + $evaluation['duration'] - time();

		if (($attemptTimeRemaining < 0) && ($attemptStartTime > 0) && ($evaluation['duration'] != self::UNLIMITED_DURATION)) {
			return true;
		}

		return false;
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
	 * Set user id
	 *
	 * @param  $val
	 * @return void
	 */
	private function setUserId($val) {
		$this->user_id = $val;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	private function getUserId() {
		return $this->user_id;
	}

	private function setEvaluation($val) {
		$this->evaluation = $val;
	}

	private function getEvaluation() {
		return $this->evaluation;
	}
}
