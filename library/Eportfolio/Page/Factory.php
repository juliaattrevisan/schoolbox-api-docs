<?php
/**
 * Eportfolio_Page_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Page_Factory {

	/**
	 * An instance of Eportfolio_Page_Abstract
	 *
	 * @var Eportfolio_Page_Abstract
	 */
	private $page = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 * @param $permissionValue
	 */
	public function __construct($page, $userId, $pageId, $permissionValue) {
		$this->setPage($this->produce($page, $userId, $pageId, $permissionValue));
	}

	/**
	 * Decide which kind of page to manufacture
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 * @param $permissionValue
	 * @return Eportfolio_Page_Abstract
	 */
	private function produce($page, $userId, $pageId, $permissionValue) {
		if (is_numeric($pageId)) {
			$eportfolioPage = new Eportfolio_Page_Instance($page, $userId, $pageId, $permissionValue);
		}
		else if ($pageId == 'showcase') {
			$eportfolioPage = new Eportfolio_Page_Showcase($page, $userId, $pageId, $permissionValue);
		}
		else if ($pageId == 'create') {
			$eportfolioPage = new Eportfolio_Page_Create($page, $userId, $pageId, $permissionValue);
		}
		else if ($pageId == 'profile') {
			$eportfolioPage = new Eportfolio_Page_Profile($page, $userId, $pageId, $permissionValue);
		}
        else {
            $page->pageNotFound();
        }

		return $eportfolioPage;
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param Eportfolio_Page_Abstract $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return Eportfolio_Page_Abstract
	 */
	public function getPage() {
		return $this->page;
	}
}