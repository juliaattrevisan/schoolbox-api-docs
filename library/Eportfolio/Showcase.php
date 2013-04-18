<?php
/**
 * Eportfolio_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;

class Showcase {

	/**
	 * An instance of a returned submission
	 *
	 * @var
	 */
	public $submissionReturn;

	/**
	 * The showcase item id
	 *
	 * @var int
	 */
	public $id = null;

	/* Getters/Setters
	 */

	/**
	 * Set id
	 *
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set submission return
	 *
	 * @param  $submissionReturn
	 */
	public function setSubmissionReturn($submissionReturn) {
		$this->submissionReturn = $submissionReturn;
	}

	/**
	 * Get submission return
	 *
	 * @return
	 */
	public function getSubmissionReturn() {
		return $this->submissionReturn;
	}


}