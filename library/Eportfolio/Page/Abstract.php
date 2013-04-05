<?php
/**
 * Eportfolio_Page_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class Eportfolio_Page_Abstract {

	/**
	 * An instance of sitepage
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * The id of the page being viewed
	 *
	 * @var int
	 */
	protected $pageId = -1;

	/**
	 * The user record for the owner of the page being viewed
	 *
	 * @var array
	 */
	protected $user = array();

	/**
	 * An instance of userHandler
	 *
	 * @var null
	 */
	protected $userHandler = null;

	/**
	 * An instance of eportfolioHandler
	 *
	 * @var null
	 */
	protected $eportfolioHandler = null;

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = '';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 */
	final public function __construct($page, $userId, $pageId) {
		$this->setPage($page);
		$this->setPageId($pageId);
		$this->setUserHandler(new User());

		/* Check that the user exists
		 */
		$this->validateUser($userId);

		/* Initialise classes and collections
		 */
		$this->initialise();

		/* Setup template and stuff
		 */
		$this->setup();
	}

	/**
	 * Validate the user id to check that the user exists
	 * If he does, remember his info
	 *
	 * @param $userId
	 */
	protected function validateUser($userId) {

		/* Retrieve the user ID from the URI, must be an integer and at least 1 character long
		 */
		if (preg_match('/^\d+$/', $userId)) {
			$user = $this->getUserHandler()->getByID($userId);
		}

		/* if we still have no user, advise that these are not the droids you're looking for
		 */
		if (!isset($user['uid'])) {
			error(18);
		}

		$this->setUser($user);
	}

	abstract protected function initialise();
	abstract protected function setup();
	abstract public function render();

	/* Getters/Setters
	 */

	/**
	 * Set page id
	 *
	 * @param int $pageId
	 */
	protected function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return int
	 */
	protected function getPageId() {
		return $this->pageId;
	}

	/**
	 * Get the user id for a person who exists
	 *
	 * @return mixed
	 */
	protected function getUserId() {
		return $this->user['uid'];
	}

	/**
	 * Get the page owner's name
	 *
	 * @return string
	 */
	protected function getUserName() {
		$user = $this->getUser();

		return Scrub::text($user['firstname'] . ' ' . $user['lastname']);
	}

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set template file
	 *
	 * @param string $templateFile
	 */
	protected function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Set user
	 *
	 * @param array $user
	 */
	protected function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return array
	 */
	protected function getUser() {
		return $this->user;
	}

	/**
	 * Set user handler
	 *
	 * @param null $userHandler
	 */
	protected function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

	/**
	 * Get user handler
	 *
	 * @return null
	 */
	protected function getUserHandler() {
		return $this->userHandler;
	}

}