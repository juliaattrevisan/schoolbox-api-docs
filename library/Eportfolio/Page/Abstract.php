<?php
/**
 * Eportfolio_Page_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \Schoolbox\User;
use \Schoolbox\Assessment\Component\SubmissionBox;

abstract class Eportfolio_Page_Abstract {

	/**
	 * An instance of sitepage
	 *
	 * @var SitePage
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
	 * @var \Schoolbox\User
	 */
	protected $user = null;

	/**
	 * An instance of userHandler
	 *
	 * @var User
	 */
	protected $userHandler = null;

	/**
	 * An instance of groupHandler
	 *
	 * @var Group
	 */
	protected $groupHandler = null;

	/**
	 * An instance of eportfolioHandler
	 *
	 * @var Eportfolio
	 */
	protected $eportfolioHandler = null;

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = '';

	/**
	 * A const equivalent corresponding to READ_ACCESS or WRITE_ACCESS etc.
	 *
	 * This value is set in the factory, where a different class determines
	 * the actual value.
	 *
	 * @var int
	 */
	protected $permission = -1;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 * @param $permission
	 */
	public function __construct($page, $userId, $pageId, $permission) {
		$this->setPage($page);
		$this->setPageId($pageId);
		$this->setUserHandler(new \Schoolbox\User());
		$this->setGroupHandler(new Group());
		$this->setPermission($permission);

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
        $user = null;
		if (preg_match('/^\d+$/', $userId)) {
            try {
                $user = \Schoolbox\UserMapper::getInstance()->findById($userId);
            }
            catch (\Schoolbox\Mapper\Exception $e) {}
		}

		/* if we still have no user, advise that these are not the droids you're looking for
		 */
		if (!($user instanceof \Schoolbox\User)) {
			error(18);
		}

		$this->setUser($user);
	}

	/**
	 * Set up blocks for the list of pages
	 */
	private function setupPageList() {
		$this->getPage()->setBlock(TEMPLATE_content, 'eportfolio_page', 'eportfolio_page_block');


		/* Optional add-page butonye
		 */
		$this->getPage()->setBlock(TEMPLATE_content, 'can_add_page', 'can_add_page_block');

		$this->getPage()->setVar('can_add_page_block', '');

		if ($this->getPermission() > WRITE_ACCESS) {
			$this->getPage()->parse('can_add_page_block', 'can_add_page');
		}

        /* Only show the showcase tab if there is something to see */
        $this->getPage()->setBlock(TEMPLATE_content, 'showcase_page', 'showcase_page_block');

        if(\Eportfolio\ShowcaseMapper::getInstance()->countByColumn('uid',$this->getUserId())) {
            $this->getPage()->parse('showcase_page_block', 'showcase_page');
        }
        else {
            $this->getPage()->setVar('showcase_page_block','');
        }

	}

	/**
	 * Render a list of pages
	 */
	private function renderPageList() {
		$pageMapper = new Eportfolio\PageMapper();

		$allPages = $pageMapper->findViewablePages($_SESSION['id'], $this->getUserId());

		$this->getPage()->setVar('eportfolio_page_block', '');

		if ($allPages->count()) {
			foreach ($allPages as $currentPage) {
				/** @var Eportfolio\Page $currentPage */
				if (!$currentPage->isDeleted()) {
					$this->getPage()->setVar(array(
						'EPORTFOLIO_PAGE_ID' => $currentPage->getPageId(),
						'EPORTFOLIO_PAGE_NAME' => $currentPage->getPageTitle(),
						'EPORTFOLIO_CURRENT_PAGE' => $currentPage->getPageId()==$this->getPageId()?'class="current"':'',
					));

					$this->getPage()->parse('eportfolio_page_block', 'eportfolio_page', true);
				}
			}
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

    /**
     * Get the latest entry for a Submission History
     *
     * @param User $user
     * @param SubmissionBox $box
     * @return mixed
     */
	protected function getLatestSubmission(User $user, SubmissionBox $box) {
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper();

		$history = $mapper->getHistory($user->getId(), $box->getId());

		if ($history->count()) {
			return $history->first()->getSubmission();
		}
	}

    /**
     * Get the latest entry for a Returned Work history
     *
     * @param User $user
     * @param SubmissionBox $box
     * @return mixed
     */
	protected function getLatestReturn(User $user, SubmissionBox $box) {
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();

		$history = $mapper->getHistory($user->getId(), $box->getId());

		if ($history->count()) {
			return $history->first()->getReturnedWork();
		}
	}

	/**
	 * Render mime types
	 */
	protected function renderMimeTypes() {
		$this->getPage()->setBlock(TEMPLATE_content, 'allowed_files', 'allowed_files_block');

		$allowedFiles = new AllowedFile();
		$allowedList = $allowedFiles->getAll();

		if (is_array($allowedList) && count($allowedList)) {

			// append link icon to results
			$allowedList[]['iconFile'] = "link.png";
			$last = '';

			foreach ($allowedList as $allowed) {
				if ($last != $allowed["iconFile"]) {
					$this->getPage()->setVar(array(
						'NAME' => $allowedFiles->getClass($allowed["iconFile"]),
						'ICON' => $allowed["iconFile"],
					));

					$this->getPage()->parse("allowed_files_block", "allowed_files", true);

					$last = $allowed["iconFile"];
				}
			}
		}
		else {
			$this->getPage()->setVar('allowed_files_block', '');
		}
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
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * Get the user id for a person who exists
	 *
	 * @return mixed
	 */
	public function getUserId() {
		return $this->user->getId();
	}

	/**
	 * Get the page owner's name
	 *
	 * @return string
	 */
	protected function getUserName() {
		return $this->getUser()->formatName();
	}

	/**
	 * Set page
	 *
	 * @param SitePage $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return SitePage
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
	 * @param \Schoolbox\User $user
	 */
	protected function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return \Schoolbox\User
	 */
	protected function getUser() {
		return $this->user;
	}

	/**
	 * Set user handler
	 *
	 * @param \Schoolbox\User $userHandler
	 */
	protected function setUserHandler($userHandler) {
		$this->userHandler = $userHandler;
	}

	/**
	 * Get user handler
	 *
	 * @return \Schoolbox\User
	 */
	protected function getUserHandler() {
		return $this->userHandler;
	}

	/**
	 * Set eportfolio handler
	 *
	 * @param Eportfolio $eportfolioHandler
	 */
	protected function setEportfolioHandler($eportfolioHandler) {
		$this->eportfolioHandler = $eportfolioHandler;
	}

	/**
	 * Get eportfolio handler
	 *
	 * @return Eportfolio
	 */
	protected function getEportfolioHandler() {
		return $this->eportfolioHandler;
	}

	/**
	 * Set group handler
	 *
	 * @param Group $groupHandler
	 */
	protected function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return Group
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set permission value
	 *
	 * @param int $permission
	 */
	public function setPermission($permission) {
		$this->permission = $permission;
	}

	/**
	 * Get permission value
	 *
	 * @return int
	 */
	public function getPermission() {
		return $this->permission;
	}
}
