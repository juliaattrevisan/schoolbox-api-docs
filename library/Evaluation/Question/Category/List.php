<?php
/**
 * Evaluation_Question_Category_List
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Evaluation_Question_Category_List {
	private $page = null;
	private $allCategories = array();
	private $selectedAssess = 0;
	private $selectedCategory = 0;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $selectedAssess
	 * @param $selectedCategory
	 * @param $allCategories
	 */
	public function __construct($page, $selectedAssess, $selectedCategory, $allCategories) {
		$this->setPage($page);
		$this->setSelectedAssess($selectedAssess);
		$this->setSelectedCategory($selectedCategory);
		$this->setAllCategories($allCategories);

		$this->setup();
	}

	/**
	 * Set up blocks and stuff
	 */
	private function setup() {
		$this->getPage()->setBlock(TEMPLATE_content, 'category_list_start', 'category_list_start_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'category_list_item_start', 'category_list_item_start_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'category_list_item', 'category_list_item_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'category_list_item_end', 'category_list_item_end_block');

		$this->getPage()->setVar(array(
			'category_list_start_block' => '',
			'category_list_item_start_block' => '',
			'category_list_item_end_block' => '',
			'question_category_block' => '',
		));
	}

	/**
	 * Render hierarchy wrapper method
	 */
	public function render() {
		$this->build($this->getAllCategories());
	}

	/**
	 * Recursively construct and render a category hierarchy
	 *
	 * @param $allCategories
	 */
	private function build($allCategories) {
		foreach($allCategories as $currentCategory) {

			/* Find out if this the category that has been selected
			 */
			if ($currentCategory['question_category_id'] == $this->getSelectedCategory()) {
				$selected = 'selected';
			}
			else {
				$selected = '';
			}

			$this->getPage()->setVar(array(
				'CATEGORY_LIST_ITEM_ASSESS_ID' => $this->getSelectedAssess(),
				'CATEGORY_LIST_ITEM_CATEGORY_ID' => $currentCategory['question_category_id'],
				'CATEGORY_LIST_ITEM_NAME' => $currentCategory['name'],
				'CATEGORY_LIST_ITEM_NUMQUESTIONS' => $currentCategory['numQuestions'],
				'CATEGORY_LIST_ITEM_SELECTED' => $selected,
			));

			$this->getPage()->parse('category_list_item_block', 'category_list_item', true);

			if (count($currentCategory['children'])) {
				$this->getPage()->parse('category_list_item_block', 'category_list_item_start', true);
				$this->build($currentCategory['children']);
				$this->getPage()->parse('category_list_item_block', 'category_list_item_end', true);
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Set all categories
	 *
	 * @param $allCategories
	 */
	private function setAllCategories($allCategories) {
		$this->allCategories = $allCategories;
	}

	/**
	 * Get all categories
	 *
	 * @return array
	 */
	private function getAllCategories() {
		return $this->allCategories;
	}

	/**
	 * Set selected category
	 *
	 * @param $selectedCategory
	 */
	private function setSelectedCategory($selectedCategory) {
		$this->selectedCategory = $selectedCategory;
	}

	/**
	 * Get selected category
	 *
	 * @return int
	 */
	private function getSelectedCategory() {
		return $this->selectedCategory;
	}

	/**
	 * Set selected assess
	 *
	 * @param $selectedAssess
	 */
	private function setSelectedAssess($selectedAssess) {
		$this->selectedAssess = $selectedAssess;
	}

	/**
	 * Get selected assess
	 *
	 * @return int
	 */
	private function getSelectedAssess() {
		return $this->selectedAssess;
	}
}
