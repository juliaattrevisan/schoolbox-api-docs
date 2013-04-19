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

		/* Render mime t ypes
		 */
		$this->renderMimeTypes();
	}

	/**
	 * Render mime types
	 */
	private function renderMimeTypes() {
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

	/**
	 * Get the latest entry for a Submission History
	 *
	 * @param $userId
	 * @param $boxId
	 * @return mixed
	 */
	private function getLatestSubmission($userId, $boxId) {
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();

		$history = $mapper->getSubmissionHistory($userId, $boxId);

		if ($history->count()) {
			return $history->current()->getSubmission();
		}
	}

	/**
	 * Get the latest entry for a Returned Work history
	 *
	 * @param $userId
	 * @param $boxId
	 * @return mixed
	 */
	private function getLatestReturn($userId, $boxId) {
		$mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();

		$history = $mapper->getSubmissionHistory($userId, $boxId);

		if ($history->count()) {
			return $history->current()->getReturnedWork();
		}
	}

	/**
	 * Render page
	 */
	protected function render() {
		$showcaseMapper = new \Eportfolio\ShowcaseMapper();
		$boxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();

		/* Gather around the loop, everyone
		 */
		$allShowcasedItems = $showcaseMapper->getShowcasedSubmissionBoxes($this->getUserId());

		if ($allShowcasedItems->count()) {
			$previousCategory = null;

			foreach($allShowcasedItems as $currentShowcasedItem) {
				$latestSubmission = $this->getLatestSubmission($this->getUserId(), $currentShowcasedItem->getSubmissionBoxId());
				$latestReturn = $this->getLatestReturn($this->getUserId(), $currentShowcasedItem->getSubmissionBoxId());
				$currentBox = $boxMapper->findById($currentShowcasedItem->getSubmissionBoxId());

				$this->getPage()->setVar(array(
					'SHOWCASED_WORK_ITEM_ID' => $currentShowcasedItem->getId(),
					'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentBox->getCategory()->getName(),
					'SHOWCASED_WORK_BOX_NAME' => $currentBox->getName(),
					'SHOWCASED_WORK_BOX_TYPE' => $currentBox->getWorkType()->getName(),
				));

				/* Show submitted work information (if available)
				 */
				if ($latestSubmission) {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_SUBMISSION_FILESIZE' => Alaress_Byte_Format::bytesToString($latestSubmission->getFilesize()),
						'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $latestSubmission->getSubmitted()->getShort(),
						'SHOWCASED_WORK_MIMETYPE_ICONFILE' => $latestSubmission->getMimetype()->getClass(),
					));
				}
				else {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_SUBMISSION_FILESIZE' => '',
						'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => '',
					));
				}

				/* Show returned work information (if available)
				 */
				if ($latestReturn) {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_RETURNED_MARK' => $latestReturn->getMark(),
					));
				}
				else {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_RETURNED_MARK' => '',
					));
				}

				/* Category name
				 */
				if (($previousCategory == null) || ($previousCategory != $currentBox->getCategory()->getName())) {
					$previousCategory = $currentBox->getCategory()->getName();

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