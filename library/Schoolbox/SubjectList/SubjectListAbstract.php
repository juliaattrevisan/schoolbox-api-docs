<?php
/**
 * SubjectList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SubjectList;


abstract class SubjectListAbstract {

	/**
	 * Washington Monument
	 *
	 * @var null
	 */
	protected $page = null;

	/**
	 * An array of all the subjects to split
	 *
	 * @var array
	 */
	protected $allSubjects = array();

	/**
	 * The resultant split array
	 *
	 * @var array
	 */
	protected $allSubjectLists = array();

	/**
	 * An instance of categoryHandler
	 *
	 * @var null
	 */
	protected $categoryHandler = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $allSubjects
	 */
	public function __construct($page, $allSubjects) {
		$this->setPage($page);
		$this->setAllSubjects($allSubjects);
		$this->setCategoryHandler(new \Category());

		$this->setup();
		$this->split();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/**
	 * Split the subjects into a set of lists
	 */
	protected function split() {
		$allSubjectLists = array();

		/* Group them up into manageable chunks because of phplib
		 * Dump subjects without a defined classType into a generic
		 * "Subjects" array element, otherwise use their provided
		 * classTypes instead
		 */
		if (is_array($this->getAllSubjects()) && count($this->getAllSubjects())) {
			foreach($this->getAllSubjects() as $currentSubject) {
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

		$this->setAllSubjectLists($allSubjectLists);
	}

	/**
	 * Render
	 */
	abstract function render();

	/* Getters/Setters
	 */

	/**
	 * Set all subjects
	 *
	 * @param array $allSubjects
	 */
	protected function setAllSubjects($allSubjects) {
		$this->allSubjects = $allSubjects;
	}

	/**
	 * Get all subjects
	 *
	 * @return array
	 */
	protected function getAllSubjects() {
		return $this->allSubjects;
	}

	/**
	 * Set category handler
	 * s
	 * @param null $categoryHandler
	 */
	protected function setCategoryHandler($categoryHandler) {
		$this->categoryHandler = $categoryHandler;
	}

	/**
	 * Get category handler
	 *
	 * @return null
	 */
	protected function getCategoryHandler() {
		return $this->categoryHandler;
	}

	/**
	 * Set all subject lists
	 *
	 * @param array $allSubjectLists
	 */
	protected function setAllSubjectLists($allSubjectLists) {
		$this->allSubjectLists = $allSubjectLists;
	}

	/**
	 * Get all subject lists
	 *
	 * @return array
	 */
	protected function getAllSubjectLists() {
		return $this->allSubjectLists;
	}

	/**
	 * Set page
	 *
	 * @param null $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}


}