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
	 * Number of Recent Showcased Items to display
	 *
	 * @var int
	 */
	protected $numShowcasedItems = -1;

	/**
	 * Number of Recent Blogs to display
	 *
	 * @var int
	 */
	protected $numRecentBlogs = -1;

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
		$this->setNumShowcasedItems(3);
		$this->setNumRecentBlogs(3);
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

		$this->getPage()->setBlock(TEMPLATE_content, 'no_showcased_work', 'no_showcased_work_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'has_showcased_work', 'has_showcased_work_block');
		$this->getPage()->setBlock('has_showcased_work', 'showcased_work', 'showcased_work_block');
		$this->getPage()->setBlock('showcased_work', 'has_submission', 'has_submission_block');
		$this->getPage()->setBlock('showcased_work', 'no_submission', 'no_submission_block');

		$this->getPage()->setBlock(TEMPLATE_content, 'no_recent_blog', 'no_recent_blog_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'has_recent_blog', 'has_recent_blog_block');
		$this->getPage()->setBlock('has_recent_blog', 'recent_blog', 'recent_blog_block');

		$user = $this->getUser();

		$this->getPage()->setVar(array(
			'USER_FIRSTNAME' => $user['firstname'],
			'USER_LASTNAME' => $user['lastname'],
		));

		/* Render mime types
		 */
		$this->renderMimeTypes();
	}

	/**
	 * Render page
	 */
	protected function render() {
		$this->renderPersonal();

		$this->renderShowcasedWork();
		$this->renderRecentPhotos();
		$this->renderRecentBlogs();

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
			'USER_EXTERNAL_ID' => $this->getExternalUserId(),
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
		$showcaseMapper = new \Eportfolio\ShowcaseMapper();
		$boxMapper = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();

		/* Gather around the loop, everyone
		 */
		$allShowcasedItems = $showcaseMapper->getRecentShowcasedSubmissionBoxes($this->getUserId(), $this->getNumShowcasedItems());

		if ($allShowcasedItems->count()) {
			$previousCategory = null;
			$j = 0;

			foreach($allShowcasedItems as $currentShowcasedItem) {
				$latestSubmission = $this->getLatestSubmission($this->getUserId(), $currentShowcasedItem->getSubmissionBoxId());
				$latestReturn = $this->getLatestReturn($this->getUserId(), $currentShowcasedItem->getSubmissionBoxId());
				$currentBox = $boxMapper->findById($currentShowcasedItem->getSubmissionBoxId());

				$this->getPage()->setVar(array(
					'SHOWCASED_WORK_ITEM_ID' => $currentShowcasedItem->getId(),
					'SHOWCASED_WORK_BOX_CATEGORY_NAME' => $currentBox->getCategory()->getName(),
					'SHOWCASED_WORK_BOX_CATEGORY_ID' => $currentBox->getCategory()->getId(),
					'SHOWCASED_WORK_BOX_NAME' => $currentBox->getName(),
					'SHOWCASED_WORK_BOX_TYPE' => $currentBox->getWorkType()->getName(),
					'ON_OFF' => ++$j % 2 ? '' : 'dark'
				));

				/* Show submitted work information (if available)
				 */
				if ($latestSubmission) {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_SUBMISSION_ID' => $latestSubmission->getId(),
						'SHOWCASED_WORK_SUBMISSION_FILESIZE' => Alaress_Byte_Format::bytesToString($latestSubmission->getFilesize()),
						'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => $latestSubmission->getSubmitted()->getShort(),
						'SHOWCASED_WORK_MIMETYPE_ICONFILE' => $latestSubmission->getMimetype()->getClass(),
					));

					$this->getPage()->parse('has_submission_block', 'has_submission');
					$this->getPage()->setVar('no_submission_block', '');
				}
				else {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_SUBMISSION_FILESIZE' => '',
						'SHOWCASED_WORK_SUBMISSION_SUBMITTED' => '',
					));

					$this->getPage()->parse('no_submission_block', 'no_submission');
					$this->getPage()->setVar('has_submission_block', '');
				}

				/* Show returned work information (if available)
				 */
				if ($latestReturn) {
					$mark = $latestReturn->getMark();
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_RETURNED_MARK' => $mark,
						'SHOWCASED_WORK_RETURNED_GRADIENT' => (int)($mark / 10),
					));
				}
				else {
					$this->getPage()->setVar(array(
						'SHOWCASED_WORK_RETURNED_MARK' => '',
						'SHOWCASED_WORK_RETURNED_GRADIENT' => ''
					));
				}

				$this->getPage()->parse('showcased_work_block', 'showcased_work', true);

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar('has_modify_form_block', '');
				$this->getPage()->setVar('has_submission_block', '');
				$this->getPage()->setVar('no_submission_block', '');
			}

			$this->getPage()->parse('has_showcased_work_block', 'has_showcased_work');
			$this->getPage()->setVar('no_showcased_work_block', '');
		}
		else {
			$this->getPage()->parse('no_showcased_work_block', 'no_showcased_work');
			$this->getPage()->setVar('has_showcased_work_block', '');
		}
	}

	/**
	 * Render recent photos
	 */
	private function renderRecentPhotos() {
	}

	/**
	 * Render recent journal posts
	 */
	private function renderRecentBlogs() {
		$allBlogs = Component_Eportfolio_BlogMapper::getInstance()->findRecent($this->getNumRecentBlogs());

		if ($allBlogs->count()) {
			$j = 0;

			foreach($allBlogs as $currentBlog) {
				$createdAt = new Alaress_Date_Relative($currentBlog->getCreatedAt());

				$this->getPage()->setVar(array(
					'RECENT_BLOG_TITLE' => $currentBlog->getTitle(),
					'RECENT_BLOG_CREATED_AT' => $createdAt->getRelativeTime(),
					'RECENT_BLOG_PAGE_ID' => $currentBlog->getPageId(),
					'ON_OFF' => ++$j % 2 ? '' : 'dark',
				));

				$this->getPage()->parse('recent_blog_block', 'recent_blog', true);
			}

			$this->getPage()->parse('has_recent_blog_block', 'has_recent_blog');
			$this->getPage()->setVar('no_recent_blog_block', '');
		}
		else {
			$this->getPage()->parse('no_recent_blog_block', 'no_recent_blog');
			$this->getPage()->setVar('has_recent_blog_block', '');
		}
	}

	/**
	 * Render groups
	 */
	private function renderGroups() {
		$allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId(), false);

		if (is_array($allGroups) && count($allGroups)) {
			$j = 0;

			foreach ($allGroups as $currentGroup) {
				$this->getPage()->setVar(array(
					'GROUP_ID' => $currentGroup['id'],
					'GROUP_NAME' => $currentGroup['name'],
					'ON_OFF' => ++$j % 2 ? '' : 'dark',
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
			$j = 0;

			foreach ($allSubjects as $currentSubject) {
				$this->getPage()->setVar(array(
					'SUBJECT_ID' => $currentSubject['id'],
					'SUBJECT_NAME' => $currentSubject['name'],
					'ON_OFF' => ++$j % 2 ? '' : 'dark',
				));

				$this->getPage()->parse('subject_block', 'subject', true);
			}

			$this->getPage()->parse('has_subjects_block', 'has_subjects');
		}
		else {
			$this->getPage()->setVar('has_subjects_block', '');
		}
	}

	/* Getters/Setters
	 */

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

	/**
	 * Set num showcased items
	 *
	 * @param int $numShowcasedItems
	 */
	private function setNumShowcasedItems($numShowcasedItems) {
		$this->numShowcasedItems = $numShowcasedItems;
	}

	/**
	 * Get num showcased items
	 *
	 * @return int
	 */
	private function getNumShowcasedItems() {
		return $this->numShowcasedItems;
	}


	/**
	 * @param int $numRecentBlogs
	 */
	private function setNumRecentBlogs($numRecentBlogs) {
		$this->numRecentBlogs = $numRecentBlogs;
	}

	/**
	 * @return int
	 */
	private function getNumRecentBlogs() {
		return $this->numRecentBlogs;
	}
}