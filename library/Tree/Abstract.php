<?php
/**
 * CategoryTree
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Tree_Abstract extends DeprecatedModel {
	public $_table = 'category';
	public $_index = 'id';

	/* The table column used for a parent id
	 */
	protected $parentIndex = 'parent';

	/* Output tree array
	 */
	private $tree = array();

	/* Handler e.g. CategoryHandler, QuestionBankHandler, etc
	 */
	private $handler = null;

	/* The ID of a leaf node
	 * Used for preselection
	 */
	private $selectedItem = null;

	/* A list of IDs from parent to a leaf node
	 */
	private $parentPath = array();

	/**
	 * Additional tasks to perform before growing tree
	 *
	 * @abstract
	 */
	abstract protected function preGrow();

	/**
	 * Additional tasks to perform after growing tree
	 *
	 * @abstract
	 */
	abstract protected function postGrow();

	/**
	 * Wrapper method which defaults a parent_id to the root level
	 * if not explicitly specified
	 *
	 * @param int $parent_id
	 *
	 * @return \Tree_Abstract
	 */
	public function grow($parent_id = 0) {
		$this->preGrow();

		$this->setTree($this->getLeaf($parent_id));

		$this->postGrow();
	}

	/**
	 * Recursively generate a list of nodes
	 *
	 * @param $parent_id
	 * @return array
	 */
	private function getLeaf($parent_id) {
		$all = array();

		$allNodes = $this->getLeafContents($parent_id);

		if (is_array($allNodes) && count($allNodes)) {
			foreach($allNodes as $currentNode) {
				$record = $currentNode;

				$record['children'] = $this->getLeaf($currentNode[$this->getIndex()]);
				$record['isParent'] = $this->isParent($currentNode[$this->getIndex()]);

				$all[] = $record;
			}
		}

		return $all;
	}

	/**
	 * Determine if this category is a parent of another
	 *
	 * @param $node_id
	 * @return int
	 */
	private function isParent($node_id) {
		$query = "
			SELECT COUNT(*) AS num
			FROM {$this->_table}
			WHERE {$this->getParentIndex()} = '$node_id'
		";

		$count = $this->db->getRow($query);

		if ($count['num'] > 0) {
			return 1;
		}

		return 0;
	}

	/**
	 * Get a list of nodes to chooze from
	 *
	 * @param int $parent_id
	 * @return array|bool
	 */
	private function getLeafContents($parent_id = 0) {
		$query = "SELECT *
				  FROM {$this->_table}
				  WHERE {$this->getParentIndex()} = '$parent_id'
				  ORDER BY name";

		return $this->db->getAll($query);
	}

	/* Getting a list of direct ancestor nodes
	 */

	/**
	 * Get a list of parent nodes with additional formatting
	 * These are all the ancestors of a child node
	 *
	 * @return string
	 */
	public function getJsAncestorNodesList() {
		$pathArray = array();

		$allPaths = $this->getAncestorsLeaf($this->getSelectedItem(), $pathArray);

		$path = array();

		/* Accumulate all the little path pieces
		 */
		if (is_array($allPaths) && count($allPaths)) {
			foreach($allPaths as $currentPath) {
				$path[] = '\'node_' . $currentPath . '\'';
			}
		}

		return implode(',', $path);
	}

	/**
	 * Get a list of parent nodes
	 * These are all the ancestors of a child node
	 *
	 * @return array
	 */
	public function getAncestorNodesList() {
		$pathArray = array();

		$allPaths = $this->getAncestorsLeaf($this->getSelectedItem(), $pathArray);

		/* Add root
		 */
		$allPaths[] = 0;

		return $allPaths;
	}

	/**
	 * Find the way back to parent
	 *
	 * @param $node_id
	 * @param $pathArray
	 *
	 * @return array
	 */
	private function getAncestorsLeaf($node_id, $pathArray) {
		if ($node_id == 0) {
			return $pathArray;
		}
		else {
			$node = $this->getHandler()->getByID($node_id);

			$pathArray[] = $node[$this->getIndex()];

			return $this->getAncestorsLeaf($node[$this->getParentIndex()], $pathArray);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Get index
	 *
	 * @return string
	 */
	protected function getIndex() {
		return $this->_index;
	}

	/**
	 * Get parent index
	 *
	 * @return string
	 */
	protected function getParentIndex() {
		return $this->parentIndex;
	}

	/**
	 * Set tree
	 *
	 * @param $tree
	 */
	private function setTree($tree) {
		$this->tree = $tree;
	}

	/**
	 * Get tree
	 *
	 * @return array
	 */
	public function getTree() {
		return $this->tree;
	}

	/**
	 * Set selected item
	 *
	 * @param $selectedItem
	 */
	public function setSelectedItem($selectedItem) {
		$this->selectedItem = $selectedItem;
	}

	/**
	 * Get selected item
	 *
	 * @return null
	 */
	protected function getSelectedItem() {
		return $this->selectedItem;
	}

	/**
	 * Set parent path
	 *
	 * @param $parentPath
	 *
	 * @return array
	 */
	protected function setParentPath($parentPath) {
		$this->parentPath = $parentPath;
	}

	/**
	 * Get parent path
	 *
	 * @return array
	 */
	public function getParentPath() {
		return $this->parentPath;
	}

	/**
	 * Set polymorphic handler
	 *
	 * @param $handler
	 */
	protected function setHandler($handler) {
		$this->handler = $handler;
	}

	/**
	 * Get polymorphic handler
	 *
	 * @return null
	 */
	protected function getHandler() {
		return $this->handler;
	}
}
