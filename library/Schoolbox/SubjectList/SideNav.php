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

		$this->getPage()->setBlock('navTpl', 'has_msubject', 'has_msubject_block');
		$this->getPage()->setBlock('has_msubject', 'msubject', 'msubject_block');
		$this->getPage()->setBlock('msubject', 'msubject_classtype', 'msubject_classtype_block');
		$this->getPage()->setBlock('msubject', 'msubject_item', 'msubject_item_block');
	}

	/**
	 * Expel each subject grouped by its type
	 */
	public function render() {
		$isStaff = $this->getGroupHandler()->isStaff();
		if (count(array_keys($this->getAllSubjectLists()))) {
			foreach($this->getAllSubjectLists() as $key => $currentSubjectList) {

				/* Respect thy authority
				 */
				if (\Config::get('mysubjects_split')) {
					$this->getPage()->setVar(array(
						'SUBJECT_CLASSTYPE' => $key
					));

					$this->getPage()->parse('msubject_classtype_block', 'msubject_classtype');
				}
				else {
					$this->getPage()->setVar(array(
						'msubject_classtype_block' => ''
					));
				}

				/* For all the classes in the current subject
				 */
				if (is_array($currentSubjectList) && count($currentSubjectList)) {
					foreach($currentSubjectList as $currentSubject) {
						$name = $currentSubject['categoryName'];
						if ($isStaff && !empty($currentSubject['subject']['code'])) {
							$name .= ' ('.$currentSubject['subject']['code'].')';
						}
						// Yes, you DO have to urlencode() this twice
						// (It gets decoded twice in /homepage/index.php)
						$encodedCode = urlencode(urlencode($currentSubject['subject']['code']));
						$this->getPage()->setVar(array(
							'SUBJECT_NAME' => \Scrub::text($name),
							'SUBJECT_URL' =>  \Scrub::text("/homepage/code/".$encodedCode),
						));

						$this->getPage()->parse('msubject_item_block', 'msubject_item', true);
					}
				}

				$this->getPage()->parse('msubject_block', 'msubject', true);

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar(array(
					'msubject_item_block' => '',
				));
			}

			$this->getPage()->parse('has_msubject_block', 'has_msubject');
		}
		else {
			$this->getPage()->setVar(array(
				'has_msubject_block' => ''
			));
		}
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * @return null
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
	}
}