<?php
/**
 * Eportfolio_Private_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Private_Abstract extends DeprecatedModel {
	protected $page = null;
	protected $componentHandle = null;
	protected $config = null;
	protected $userId = null;

	/**
	 * Constructor
	 *
	 * Don't forget to call parent constructor otherwise you won't
	 * have a reference to a db instance
	 *
	 * @param  $page
	 * @param  $componentLocationId
	 */
	public function __construct($page, $userId) {
		$this->setPage($page);
		$this->setUserId($userId);

		parent::DeprecatedModel();
	}

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
	 * @return
	 */
	protected function getUserId() {
		return $this->userId;
	}

	/**
	 * Set page
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setPage($val) {
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
	 * Get component handle
	 *
	 * @return null
	 */
	protected function getComponentHandle() {
		return $this->componentHandle;
	}

	/**
	 * Set component handle
	 *
	 * @param  $val
	 * @return void
	 */
	public function setComponentHandle($val) {
		$this->componentHandle = $val;
	}
}
