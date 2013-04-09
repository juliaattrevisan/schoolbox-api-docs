<?php
/**
 * Eportfolio_Page_Create
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Page_Create extends Eportfolio_Page_Abstract {

	/*** The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = 'eportfolio/create.html';

	/**
	 * Initialise classes and collections
	 */
	protected function initialise() {
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
		$this->getPage()->setBlock(TEMPLATE_content, 'page_template_list', 'page_template_list_block');

		$this->getPage()->setBlock(TEMPLATE_content, 'has_group_permissions', 'has_group_permissions_block');
		$this->getPage()->setBlock('has_group_permissions', 'group_permission', 'group_permission_block');
	}

	/**
	 * Render page
	 */
	protected function render() {
		$this->renderTemplateList();
		$this->renderGroupPermissions();
		$this->renderIndividualPermissions();
	}

	/**
	 * Render template list
	 */
	private function renderTemplateList() {
		$allTemplates = $this->getEportfolioHandler()->fetchAllTemplates();

		if (is_array($allTemplates) && count($allTemplates)) {
			foreach($allTemplates as $id => $name) {
				$this->getPage()->setVar(array(
					'PAGE_TEMPLATE_ID' => $id,
					'PAGE_TEMPLATE_NAME' => $name,
				));

				$this->getPage()->parse('page_template_list_block', 'page_template_list', true);
			}
		}
		else {
			$this->getPage()->setVar('page_template_list_block', '');
		}
	}

	/**
	 * Render group permissions
	 */
	private function renderGroupPermissions() {
		$allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId(), false);

		if (is_array($allGroups) && count($allGroups)) {
			foreach ($allGroups as $currentGroup) {
				$this->getPage()->setVar(array(
					'GROUP_PERMISSION_GROUP_ID' => $currentGroup['id'],
					'GROUP_PERMISSION_NAME' => $currentGroup['name'],
				));

				$this->getPage()->parse('group_permission_block', 'group_permission', true);
			}

			$this->getPage()->parse('has_group_permissions_block', 'has_group_permissions');
		}
		else {
			$this->getPage()->setVar('has_group_permissions_block', '');
		}
	}

	/**
	 * Render individual permissions
	 */
	private function renderIndividualPermissions() {
		$this->getPage()->setVar(array(
			'STUDENT_GROUP_IDS' => implode(',', $this->getGroupHandler()->getStudentGroupTypeIds())
		));
	}
}