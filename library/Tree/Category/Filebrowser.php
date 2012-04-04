<?php
/**
 * Tree_Category_Filebrowser
 *
 * Contains rendering methods used on the filebrowser.html page when
 * adding assets via tinyMCE
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Tree_Category_Filebrowser extends Tree_Category {
	private $page = null;


	/**
	 * Constructor
	 *
	 * @param $page
	 */
	public function __construct($page) {
		parent::__construct();

		$this->setPage($page);
	}

	/**
	 * Facade method
	 *
	 * Render list
	 */
	public function render() {
		$this->setup();

		$this->setParentPath($this->getAncestorNodesList($this->getSelectedItem()));

		$this->build($this->getTree());
	}

	/**
	 * Set up blocks and stuff
	 */
	private function setup() {
		$this->getPage()->setBlock(TEMPLATE_body, 'category_list_start', 'category_list_start_block');
		$this->getPage()->setBlock(TEMPLATE_body, 'category_list_item_start', 'category_list_item_start_block');
		$this->getPage()->setBlock(TEMPLATE_body, 'category_list_item', 'category_list_item_block');
		$this->getPage()->setBlock(TEMPLATE_body, 'category_list_item_end', 'category_list_item_end_block');

		$this->getPage()->parse('category_list_start_block', 'category_list_start', true);

		$this->getPage()->setVar(array(
			'category_list_item_start_block' => '',
			'category_list_item_end_block' => '',
		));
	}

	/**
	 * Recursively construct and render a category hierarchy
	 *
	 * @param $allCategories
	 */
	private function build($allCategories) {
		$groupHandler = new Group();

		if (is_array($allCategories) && count($allCategories)) {
			foreach($allCategories as $currentCategory) {

				/* Find out if this the category that has been selected
				 */
				if ($currentCategory[$this->getIndex()] == $this->getSelectedItem()) {
					$selected = 'selected';
				}
				else {
					$selected = '';
				}

				/* Permissions
				 */
				$permission[$currentCategory['id']] = $groupHandler->getPermission($currentCategory['id']);

				if ($permission[$currentCategory['id']] >= READ_ACCESS) {
					$class = array();

					$class[] = 'child-of-node-' . $currentCategory['parent'];

					/* check upload permissions
					 */
					if ($permission[$currentCategory['id']] == WRITE_ACCESS) {
						$class[] = 'write';
					}

					/* add plus sign to parent nodes
					 */
					if ($currentCategory['isParent'] == '1') {
						$class[] = in_array($currentCategory['id'], $this->getParentPath()) ? 'expanded loaded' : 'collapsed';
					}

					/* append code to name
					 */
					$name = Scrub::text($currentCategory['name']);

					if (!empty($currentCategory['code']) && $currentCategory['code'] != $currentCategory['name']) {
						$name .= ' (' . Scrub::text($currentCategory['code']) . ')';
					}

					/* select default dir
					 */
					if ($currentCategory['id'] == $this->getSelectedItem()) {
						$class[] = 'selected';
					}

					$this->getPage()->setVar(array(
						'CATEGORY_LIST_ITEM_NODE' => 'node_' . $currentCategory[$this->getIndex()],
						'CATEGORY_LIST_ITEM_NAME' => $name,
						'CATEGORY_LIST_ITEM_SELECTED' => $selected,
						'CLASS' => implode(' ', $class),
					));

					$this->getPage()->parse('category_list_item_block', 'category_list_item', true);

					if (count($currentCategory['children'])) {
						$this->getPage()->parse('category_list_item_block', 'category_list_item_start', true);
						$this->build($currentCategory['children']);
						$this->getPage()->parse('category_list_item_block', 'category_list_item_end', true);
					}
				}
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}
}
