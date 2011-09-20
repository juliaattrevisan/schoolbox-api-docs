<?php
/**
 * Eportfolio_Objective_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Objective_Abstract extends DeprecatedModel {
	protected $userId = null;
	protected $componentLocationId = null;
	protected $page = null;

	/* This is the public-facing representation of the
	 * primary key used in the db
	 */
	protected $facadePrimaryKey = '';

	/**
	 * Constructor
	 *
	 * Don't forget to call parent constructor otherwise you won't
	 * have a reference to a db instance
	 *
	 * @param  $page
	 * @param  $componentLocationId
	 */
	public function __construct($page, $componentLocationId) {
		$this->setPage($page);
		$this->setComponentLocationId($componentLocationId);

		parent::DeprecatedModel();
	}

	/**
	 * Special formatting. By default just return what was entered.
	 *
	 * @param  $body
	 * @return string
	 */
	public function getBody($body) {
		return $body;
	}

	/**
	 * Turns newline-separated body into list element
	 *
	 * @param  $body
	 * @return string
	 */
	protected function listify($body) {
		return '<ul><li>' . str_replace("\n", '</li><li>', Scrub::html(trim($body))) . '</li></ul>';
	}

	/**
	 * Get the Objectives components for a user
	 *
	 * @param  $user_id
	 * @return
	 */
	public function getForUser($user_id) {
		$query = "SELECT * FROM {$this->_table} WHERE user_id = '$user_id'";

		return $this->db->getAll($query);
	}

	/**
	 * Get a textbox record using only a componentLocationID
	 *
	 * @param  $componentLocationID
	 * @return array|bool
	 */
	public function getByComponentLocationID($componentLocationID) {
		$query = "SELECT * FROM {$this->_table} WHERE component_location_id = '$componentLocationID'";

		return $this->db->getRow($query);
	}

	/**
	 * Delete a textbox only needing to know what the componentLocation is,
	 * since the textboxID could be empty in the case that they've created the
	 * component but not added content
	 *
	 * @param  $componentLocationID
	 * @return void
	 */
	public function delete() {
		$query = "SELECT id FROM {$this->_table} WHERE component_location_id = '{$this->getComponentLocationId()}'";

		$record = $this->db->getRow($query);

		parent::delete($record['id']);
	}

	/**
	 * Perform a create/update
	 *
	 * @param  $post
	 * @return void
	 */
	public function createUpdate($post) {
		$eportfolioHandler = new ePortfolio($_SESSION['id']);

		if (!$eportfolioHandler->canEdit($this->getUserId())) {
			error(19);
		}

		$data = array(
			'user_id' => $this->getUserId(),
			'component_location_id' => Scrub::text($post['componentLocationID']),
			'body' => Scrub::html($post['body']),
		);

		/* If an ID was provided, update existing record; otherwise create
		 */
		if (is_numeric($post[$this->getFacadePrimaryKey()])) {
			$data['id'] = Scrub::text($post[$this->getFacadePrimaryKey()]);

			$this->update($data);
		}
		else {
			$this->create($data);
		}

		SitePage::localRedir('/eportfolio/' . $this->getUserId());
	}

	/* Getters/Setters
	 */

	/**
	 * Set user id
	 *
	 * @param  $val
	 * @return void
	 */
	public function setUserId($val) {
		$this->userId = $val;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	protected function getUserId() {
		return $this->userId;
	}

	/**
	 * Get Component Location Id
	 *
	 * @return null
	 */
	protected function getComponentLocationId() {
		return $this->componentLocationId;
	}

	/**
	 * Set Component Location Id
	 *
	 * @param  $val
	 * @return void
	 */
	public function setComponentLocationId($val) {
		$this->componentLocationId = $val;
	}

	/**
	 * Set page
	 *
	 * @param  $val
	 * @return void
	 */
	public function setPage($val) {
		$this->page = $val;
	}

	/**
	 * Get page
	 *
	 * @return
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Get the public-facing representation of the
	 * primary key used in the db
	 *
	 * @return string
	 */
	protected function getFacadePrimaryKey() {
		return $this->facadePrimaryKey;
	}
}
