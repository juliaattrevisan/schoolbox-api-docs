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

		/* If the user is valid, initiate everything
		 */
		$this->setEportfolioHandler(new Eportfolio($this->getUserId(), $this->getPageId()));

		/* Initialise classes and collections
		 */
		$this->initialise();

		/* Setup template and stuff
		 */
		$this->setup();

		/* Set up page list
		 */
		$this->setupPageList();
	}

	abstract protected function initialise();
	abstract protected function setup();
	abstract protected function render();

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

	/**
	 * Set up blocks for the list of pages
	 */
	private function setupPageList() {
		$this->getPage()->setBlock(TEMPLATE_content, 'eportfolio_page', 'eportfolio_page_block');
	}

	/**
	 * Render a list of pages
	 */
	private function renderPageList() {
		$allPages = $this->getEportfolioHandler()->fetchAllPages();

		if (is_array($allPages) && count($allPages)) {
			foreach($allPages as $currentPage) {
				$this->getPage()->setVar(array(
					'EPORTFOLIO_PAGE_USER_ID' => $this->getUserId(),
					'EPORTFOLIO_PAGE_ID' => $currentPage['page_id'],
					'EPORTFOLIO_PAGE_NAME' => $currentPage['page_title'],
				));

				$this->getPage()->parse('eportfolio_page_block', 'eportfolio_page', true);
			}
		}
		else {
			$this->getPage()->setVar('eportfolio_page_block', '');
		}

		/* For the hardcoded pages
		 */
		$this->getPage()->setVar(array(
			'EPORTFOLIO_PAGE_USER_ID' => $this->getUserId(),
		));
	}

	/**
	 * Common elements rendered on all pages can be established here
	 */
	public function renderPage() {

		/* Render page list
		 */
		$this->renderPageList();

		/* A rendering hook method
		 */
		$this->render();
	}

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

	/**
	 * Set eportfolio handler
	 *
	 * @param null $eportfolioHandler
	 */
	protected function setEportfolioHandler($eportfolioHandler) {
		$this->eportfolioHandler = $eportfolioHandler;
	}

	/**
	 * Get eportfolio handler
	 *
	 * @return null
	 */
	protected function getEportfolioHandler() {
		return $this->eportfolioHandler;
	}
}