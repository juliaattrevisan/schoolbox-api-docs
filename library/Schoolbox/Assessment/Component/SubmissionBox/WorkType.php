<?php
/**
 * Model for submission box work types.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;
use Schoolbox\Resource;

class WorkType {

	const TYPE_ASSESSMENT_TASK   = 1;
	const TYPE_ASSIGNMENT        = 2;
	const TYPE_HOMEWORK          = 3;
	const TYPE_ESSAY             = 5;
	const TYPE_RESEARCH_PROJECT  = 6;
	const TYPE_CLASS_WORK        = 7;
	const TYPE_PRESENTATION      = 8;
	const TYPE_ORAL              = 9;
	const TYPE_EXTENSION         = 10;
	const TYPE_ONLINE_ASSESSMENT = 11;

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @param int $id
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
	 * @param string $name
	 *
	 * @return $this
	 */
	public function setName($name) {
		$this->name = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
}
