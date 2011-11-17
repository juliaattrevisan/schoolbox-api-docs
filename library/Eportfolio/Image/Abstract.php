<?php
/**
 * Eportfolio_Image_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Eportfolio_Image_Abstract extends DeprecatedModel implements Eportfolio_Image_Interface {
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
	 * @param  $userId
	 * @param  $config
	 * @param  $componentHandle
	 */
	public function __construct($page, $userId, $config, $componentHandle) {
		$this->setPage($page);
		$this->setUserId($userId);
		$this->setConfig($config);
		$this->setComponentHandle($componentHandle);

		parent::DeprecatedModel();
	}

	/**
	 * Set user id
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setUserId($val) {
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
	 * Set config
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setConfig($val) {
		$this->config = $val;
	}

	/**
	 * Get config
	 *
	 * @return void
	 */
	protected function getConfig() {
		return $this->config;
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
