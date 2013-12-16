<?php
/**
 * SideNav
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SubjectList;


class SideNav extends SubjectListAbstract {

	/**
	 * Expel each subject grouped by its type
	 */
	public function render() {
		$groupHandler = new \Group();

		foreach($this->getAllSubjectLists() as $key => $currentSubjectList) {
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
						'SUBJECT_NAME' => \Scrub::text($currentSubject['categoryName']),
						'SUBJECT_URL' => is_array($currentSubject['category']) && $groupHandler->getPermission($currentSubject['category']['id']  ) >= READ_ACCESS ? "/homepage/{$currentSubject['subject']['code']}": '#',
					));

					$this->getPage()->parse('subject_item_block', 'subject_item', true);

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

			$this->getPage()->parse('subject_block', 'subject', true);

			/* Clear children after parsing out the parent
			 */
			$this->getPage()->setVar(array(
				'subject_row_block' => '',
				'subject_item_block' => '',
			));
		}
	}
} 