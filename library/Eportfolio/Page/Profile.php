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

		$this->getPage()->setBlock(TEMPLATE_content, 'has_subjects', 'has_subjects_block');
		$this->getPage()->setBlock('has_subjects', 'subject', 'subject_block');

		$this->getPage()->setBlock(TEMPLATE_content, 'has_groups', 'has_groups_block');
		$this->getPage()->setBlock('has_groups', 'group', 'group_block');
	}

	/**
	 * Render page
	 */
	protected function render() {
		$this->renderPersonal();

		$this->renderShowcasedWork();
		$this->renderRecentPhotos();
		$this->renderRecentJournals();

		$this->renderSubjects();
		$this->renderGroups();
	}

	/**
	 * Render personal details
	 */
	private function renderPersonal() {
		$house = HouseMapper::getInstance()->findById($this->getUserHouse());

		if ($house) {
			$houseName = $house->getHouse();
		}

		$this->getPage()->setVar(array(
			'USER_ID' => $this->getExternalUserId(),
			'USER_FULLNAME' => $this->getUserName(),
			'USER_CAMPUS' => $this->getUserCampusName(),
			'USER_YEAR' => $this->getUserYear(),
			'USER_HOUSE' => $houseName,
		));
	}

	/**
	 * Render recent showcased work
	 */
	private function renderShowcasedWork() {
	}

	/**
	 * Render recent photos
	 */
	private function renderRecentPhotos() {
	}

	/**
	 * Render recent journal posts
	 */
	private function renderRecentJournals() {
	}

	/**
	 * Render groups
	 */
	private function renderGroups() {
		$allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId(), false);

		if (is_array($allGroups) && count($allGroups)) {
			foreach ($allGroups as $currentGroup) {
				$this->getPage()->setVar(array(
					'GROUP_ID' => $currentGroup['id'],
					'GROUP_NAME' => $currentGroup['name'],
				));

				$this->getPage()->parse('group_block', 'group', true);
			}

			$this->getPage()->parse('has_groups_block', 'has_groups');
		}
		else {
			$this->getPage()->setVar('has_groups_block', '');
		}
	}

	/**
	 * Render subjects
	 */
	private function renderSubjects() {
		$allSubjects = $this->getGroupHandler()->involvedInSubjects($this->getUserId());

		if (is_array($allSubjects) && count($allSubjects)) {
			foreach ($allSubjects as $currentSubject) {
				$this->getPage()->setVar(array(
					'SUBJECT_ID' => $currentSubject['id'],
					'SUBJECT_NAME' => $currentSubject['name'],
				));

				$this->getPage()->parse('subject_block', 'subject', true);
			}

			$this->getPage()->parse('has_subjects_block', 'has_subjects');
		}
		else {
			$this->getPage()->setVar('has_subjects_block', '');
		}
	}

	/**
	 * Get a user's house id
	 *
	 * @return mixed
	 */
	private function getUserHouse() {
		return $this->user['house'];
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