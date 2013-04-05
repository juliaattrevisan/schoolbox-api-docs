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
	 * @var null
	 */
	private $page = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 */
	public function __construct($page, $userId, $pageId) {
		$this->setPage($this->produce($page, $userId, $pageId));
	}

	/**
	 * Decide which kind of page to manufacture
	 *
	 * @param $page
	 * @param $userId
	 * @param $pageId
	 * @return \Eportfolio_Page_Create|\Eportfolio_Page_Instance|\Eportfolio_Page_Profile|\Eportfolio_Page_Showcase
	 */
	private function produce($page, $userId, $pageId) {
		if (is_numeric($pageId)) {
			$eportfolioPage = new Eportfolio_Page_Instance($page, $userId, $pageId);
		}
		else if ($pageId == 'showcase') {
			$eportfolioPage = new Eportfolio_Page_Showcase($page, $userId, $pageId);
		}
		else if ($pageId == 'create') {
			$eportfolioPage = new Eportfolio_Page_Create($page, $userId, $pageId);
		}
		else {
			$eportfolioPage = new Eportfolio_Page_Profile($page, $userId, $pageId);
		}

		return $eportfolioPage;
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	public function getPage() {
		return $this->page;
	}
}