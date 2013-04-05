<?php
/**
 * Eportfolio_Page_Profile
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Page_Profile extends Eportfolio_Page_Abstract {

	/**
	 * An instance of groupHandler
	 *
	 * @var null
	 */
	private $groupHandler = null;

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = 'eportfolio/profile.html';

	/**
	 * Initialise classes and collections
	 */
	protected function initialise() {
		$this->setGroupHandler(new Group());
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
	}

	/**
	 * Render page
	 */
	public function render() {
		$this->renderPersonal();
		$this->renderSubjects();


	}

	/**
	 * Render personal details
	 */
	private function renderPersonal() {
		$this->getPage()->setVar(array(
			'USER_ID' => $this->getExternalUserId(),
			'USER_FULLNAME' => $this->getUserName(),
			'USER_CAMPUS' => $this->getUserCampusName(),
			'USER_YEAR' => $this->getUserYear(),
		));
	}

	private function renderSubjects() {
		$catList = $this->getGroupHandler()->involvedInCategoryList($this->getUserId());

		print_r($catList);
	}

	/**
	 * Get a user's year level
	 *
	 * @return mixed
	 */
	private function getUserYear() {
		return $this->user['year'];
	}

	/**
	 * Get a user's external id
	 *
	 * @return mixed
	 */
	private function getExternalUserId() {
		return $this->user['synergy_id'];
	}

	/**
	 * Get a user's campus name
	 *
	 * @return mixed
	 */
	private function getUserCampusName() {
		return $this->user['campusName'];
	}

	/* Getters/Setters
	 */

	/**
	 * Set group handler
	 *
	 * @param null $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
	}
}