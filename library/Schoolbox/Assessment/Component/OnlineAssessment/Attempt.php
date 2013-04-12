<?php
/**
 * Model for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\User;

class Attempt {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var OnlineAssessment
	 */
	protected $assessment;

	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $start;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $finish;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $reviewed;

	/**
	 * @param  \Schoolbox\Assessment\Component\OnlineAssessment $assessment
	 *
	 * @return $this
	 */
	public function setAssessment($assessment) {
		$this->assessment = $assessment;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\OnlineAssessment
	 */
	public function getAssessment() {
		return $this->assessment;
	}

	/**
	 * @param  \Alaress_Date_Format $finish
	 *
	 * @return $this
	 */
	public function setFinish($finish) {
		$this->finish = $finish;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getFinish() {
		return $this->finish;
	}

	/**
	 * @param  int $id
	 *
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  \Alaress_Date_Format $reviewed
	 *
	 * @return $this
	 */
	public function setReviewed($reviewed) {
		$this->reviewed = $reviewed;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getReviewed() {
		return $this->reviewed;
	}

	/**
	 * @param  \Alaress_Date_Format $start
	 *
	 * @return $this
	 */
	public function setStart($start) {
		$this->start = $start;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @param  \Schoolbox\User $user
	 *
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}

}
