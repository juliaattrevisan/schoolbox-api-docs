<?php
/**
 * Eportfolio_Page_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Page_Showcase extends Eportfolio_Page_Abstract {

	/**
	 * The template file to use
	 *
	 * @var string
	 */
	protected $templateFile = 'eportfolio/showcase.html';

	/**
	 * Initialise classes and collections
	 */
	protected function initialise() {}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
		$this->getPage()->setBlock(TEMPLATE_content, 'has_showcased_work', 'has_showcased_work_block');
		$this->getPage()->setBlock('has_showcased_work', 'showcased_work', 'showcased_work_block');
		$this->getPage()->setBlock('showcased_work', 'showcased_work_category', 'showcased_work_category_block');
		$this->getPage()->setBlock('showcased_work', 'has_modify_form', 'has_modify_form_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'no_showcased_work', 'no_showcased_work_block');

		$user = $this->getUser();

		$this->getPage()->setVar(array(
			'USER_FIRSTNAME' => $user['firstname'],
			'USER_LASTNAME' => $user['lastname'],
		));
	}

	/**
	 * Render page
	 */
	protected function render() {
		$snapper = new \Eportfolio\ShowcaseMapper();

		$allShowcasedItems = $snapper->findShowcasedWork($this->getUserId());

		if ($allShowcasedItems->count()) {
			$previousCategory = null;

			foreach($allShowcasedItems as $currentShowcasedItem) {
				$currentCategory = $currentShowcasedItem->getSubmissionReturn()->getSubmissionBox()->getCategory();
				$currentReturned = $currentShowcasedItem->getSubmissionReturn();
				$currentSubmission = $currentShowcasedItem->getSubmission();
				$currentBox = $currentReturned->getSubmissionBox();

				$this->getPage()->setVar(array(
					'SHOWCASED_WORK_ITEM_ID' => $currentShowcasedItem->getId(),
					'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentCategory->getName(),
					'SHOWCASED_WORK_BOX_NAME' => $currentBox->getName(),
					'SHOWCASED_WORK_BOX_TYPE' => $currentBox->getWorkType()->getName(),
					'SHOWCASED_WORK_SUBMISSION_FILESIZE' => $currentSubmission->getFilesize(),
					'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $currentSubmission->getSubmitted()->getShort(),
					'SHOWCASED_WORK_RETURNED_MARK' => $currentReturned->getMark(),
				));

				/* Category name
				 */
				if (($previousCategory == null) || ($previousCategory != $currentCategory->getName())) {
					$previousCategory = $currentCategory->getName();

					$this->getPage()->parse('showcased_work_category_block', 'showcased_work_category');
				}

				/* Unpublish control
				 */
				if ($this->getPermission() >= WRITE_ACCESS) {
					$this->getPage()->parse('has_modify_form_block', 'has_modify_form');
				}
				else {
					$this->getPage()->setVar('has_modify_form_block', '');
				}

				$this->getPage()->parse('showcased_work_block', 'showcased_work', true);

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar('showcased_work_category_block', '');
				$this->getPage()->setVar('has_modify_form_block', '');
			}

			$this->getPage()->parse('has_showcased_work_block', 'has_showcased_work');
			$this->getPage()->setVar('no_showcased_work_block', '');
		}
		else {
			$this->getPage()->parse('no_showcased_work_block', 'no_showcased_work');
			$this->getPage()->setVar('has_showcased_work_block', '');
		}
	}
}