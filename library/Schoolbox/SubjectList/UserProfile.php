<?php
/**
 * UserProfile
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SubjectList;


class UserProfile extends SubjectListAbstract{

	/**
	 * An instance of groupHandler
	 *
	 * @var null
	 */
	private $groupHandler = null;

	/**
	 * Hook method
	 *
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->setGroupHandler(new \Group());

		$this->getPage()->setBlock(TEMPLATE_content, 'subject', 'subject_block');
		$this->getPage()->setBlock('subject', 'subject_row', 'subject_row_block');
		$this->getPage()->setBlock('subject', 'subject_mail', 'subject_mail_block');
		$this->getPage()->setBlock('subject_row', 'subject_item', 'subject_item_block');
		$this->getPage()->setBlock('subject_item', 'subject_teacher', 'subject_teacher_block');

		$this->getPage()->setVar(array(
			'subject_block' => '',
			'subject_row_block' => '',
			'subject_mail_block' => '',
			'subject_item_block' => '',
			'subject_teacher_block' => '',
		));
	}

	/**
	 * Expel each subject grouped by its type
	 */
	public function render() {
		$allSubjectLists = array();

		/* Group them up into manageable chunks because of phplib
		 * Dump subjects without a defined classType into a generic
		 * "Subjects" array element, otherwise use their provided
		 * classTypes instead
		 */
		if (is_array($this->getAllSubjectLists()) && count($this->getAllSubjectLists())) {
			foreach($this->getAllSubjectLists() as $currentSubject) {
				$category = $this->getCategoryHandler()->getByCode($currentSubject['code']);

				/* If the homepage doesn't exist or can't be found, don't
				 * link it in the Subject list
				 */
				if (is_numeric($category['id'])) {
					$childElement = array(
						'subject' => $currentSubject,
						'category' => $category,
						'categoryName' => is_array($category) ? $category['name'] : $currentSubject['name']
					);

					/* Subjects without a parent just get thrown together into
					 * a generic container
					 */
					if (!strlen($currentSubject['classType'])) {
						$allSubjectLists['Subjects'][] = $childElement;
					}
					else {
						$allSubjectLists[$currentSubject['classType']][] = $childElement;
					}
				}
			}
		}

		$synClass = new \External_Class();
		$userHandler = new \User();

		/* Expel each subject grouped by its type
		 */
		foreach($allSubjectLists as $key => $currentSubjectList) {
			$emailAll = array();

			$this->getPage()->setVar(array(
				'SUBJECT_CLASSTYPE' => $key
			));

			/* For all the classes in the current subject
			 */
			if (is_array($currentSubjectList) && count($currentSubjectList)) {
				$listItems = count($currentSubjectList)>1 ? ceil(count($currentSubjectList)/2) : 1;
				$i = 1;

				foreach($currentSubjectList as $currentSubject) {
					$this->getPage()->setVar(array(
						'subject_teacher_block' => ''
					));

					if ((Cfg::get('show_all_subjects') == 1) || $currentSubject['category']['id'] ) {
						$this->getPage()->setVar(array(
							'SUBJECT_NAME' => \Scrub::text($currentSubject['categoryName']),
							'SUBJECT_URL' => is_array($currentSubject['category']) && $this->getGroupHandler()->getPermission($currentSubject['category']['id']  ) >= READ_ACCESS ? "/homepage/{$currentSubject['subject']['code']}": '#',
						));

						/* Fetch teacher names
						 */
						if ($currentSubject['campusCode']) {
							$code = $currentSubject['subject']['campusCode'].'/'.$currentSubject['subject']['code'];
						}
						else {
							$code = $currentSubject['subject']['code'];
						}

						$teacherList = $synClass->getTeacherName($code);

						if (is_array($teacherList) && count($teacherList)) {
							foreach ($teacherList as $teacherSynID) {
								$teacher = $userHandler->getBySynergyAndGroup($teacherSynID, $this->getGroupHandler()->getStaffGroupTypeIds());

								if (is_array($teacher)) {
									$teacherName = $userHandler->formatName($teacher);

									$emailObj = new \Email();
									$emailAddress = $emailObj->formatEmailFromArray($teacher);
									$emailAll[] = $emailAddress;

									$this->getPage()->setVar(array(
										'SUBJECT_TEACHER_ID' => $teacher['uid'],
										'SUBJECT_TEACHER_EXTERNAL_ID' => $teacher['synergy_id'],
										'SUBJECT_TEACHER_NAME' => $teacherName,
										'SUBJECT_TEACHER_EMAIL' => $_SESSION['mailhandle'] . $emailAddress,
									));

									$this->getPage()->parse('subject_teacher_block', 'subject_teacher', true);
								}
							}
						}

						$this->getPage()->parse('subject_item_block', 'subject_item', true);

						/* Clear children after parsing out the parent
						 */
						$this->getPage()->setVar(array(
							'subject_teacher_block' => ''
						));

						/* Distribute half n' half
						 */
						if ($i % $listItems == 0) {
							$this->getPage()->parse('subject_row_block', 'subject_row', true);

							/* Clear children after parsing out the parent
							 */
							$this->getPage()->setVar(array(
								'subject_item_block' => ''
							));

							$i = 0;
						}
						else {
							$i++;
						}
					}
				}

				/* Output email all subject teachers */
				if ($this->getGroupHandler()->isStaff()) {
					$this->getPage()->setVar("REPLYTO", $emailObj->formatEmailFromArray($_SESSION));
					$this->getPage()->setVar("EMAILALL", implode(',', $emailAll));
					$this->getPage()->parse("subject_mail_block", "subject_mail");
				}
				else {
					$this->vsetVar('subject_mail_block', '');
				}
			}

			$this->getPage()->parse('subject_block', 'subject', true);

			/* Clear children after parsing out the parent
			 */
			$this->getPage()->setVar(array(
				'subject_row_block' => '',
				'subject_item_block' => '',
			));
		}
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