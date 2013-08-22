<?php
/**
 * Node
 *
 * Render a list of categories and files which sit inside
 * a particular category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Browser;


class Node {

	/**
	 * A node id, usually a primary key
	 *
	 * @var int
	 */
	private $nodeId = 0;

	/**
	 * Resultant node information
	 *
	 * @var array
	 */
	private $result = array();

	/**
	 * An instance of a category mapper
	 *
	 * @var null
	 */
	private $categoryMapper = null;

	/**
	 * Constructor
	 *
	 * $nodeId is the primary key of the category whose contents
	 * you wish to present
	 *
	 * @param $nodeId
	 */
	public function __construct($nodeId) {
		if (is_numeric($nodeId)) {
			$this->setNodeId($nodeId);
			$this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

			$this->processNode();
		}
	}

	/**
	 * Render it out in a json format in case you need it
	 */
	public function ajax() {
		header("Content-type: application/json");
		echo json_encode($this->getResult());
	}

	/**
	 * Render it out so phplib can do it
	 *
	 * @return array
	 */
	public function render() {
		return $this->getResult();
	}

	/**
	 * Combine the categories and files into a single list
	 */
	private function processNode() {
		$output = array(
			'breadcrumbs' => $this->getBreadcrumbs(),
			'records' => array_merge($this->getCategories(), $this->getFiles()),
		);

		$this->setResult($output);
	}

	/**
	 * Get a list of breadcrumbs for this node
	 *
	 * @return array
	 */
	private function getBreadcrumbs() {
		$category = $this->getCategoryMapper()->findById($this->getNodeId());

		$breadcrumbs = array();

		$breadcrumbs[] = array(
			'categoryId' => $category->getId(),
			'name' => $category->getName(),
		);

		for ($i = 0; ($category->getParentId() > 0) && ($i < 100); $i++) {
			$currentCategory = $this->getCategoryMapper()->findById($category->getParentId());

			$breadcrumbs[] = array(
				'categoryId' => $currentCategory->getId(),
				'name' => $currentCategory->getName(),
			);

			$category = $currentCategory;
		}

		return $breadcrumbs;
	}

	/**
	 * Get all the subcategories for a particular category
	 *
	 * @return array
	 */
	private function getCategories() {
		$output = array();

		$allCategories = $this->getCategoryMapper()->findByParent($this->getNodeId());

		foreach($allCategories as $currentCategory) {
			$record = array(
				'categoryId' => $currentCategory->getId(),
				'name' => $currentCategory->getName()
			);

			$output[] = $record;
		}

		return $output;
	}

	/**
	 * Get all the files for a particular category
	 *
	 * @return array
	 */
	private function getFiles() {
		$output = array();

		$fileMapper = new \Schoolbox\Resource\FileMapper();
		$mimetypeMapper = new \Schoolbox\File\MimetypeMapper();

		$allFiles = $fileMapper->findByColumn('category', $this->getNodeId(), false);

		foreach($allFiles as $currentFile) {
			$record = array(
				'fileId' => $currentFile->getId(),
				'name' => $currentFile->getFilename(),
				'icon' => $mimetypeMapper->getIconForFilename($currentFile->getFilename()),
			);

			$output[] = $record;
		}

		return $output;
	}

	/* Getters/Setters
	 */

	/**
	 * Set node id
	 *
	 * @param int $nodeId
	 */
	private function setNodeId($nodeId) {
		$this->nodeId = $nodeId;
	}

	/**
	 * Get node id
	 *
	 * @return int
	 */
	private function getNodeId() {
		return $this->nodeId;
	}

	/**
	 * Set result
	 *
	 * @param array $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * Get result
	 *
	 * @return array
	 */
	private function getResult() {
		return $this->result;
	}

	/**
	 * Set category mapper
	 *
	 * @param null $categoryMapper
	 */
	private function setCategoryMapper($categoryMapper) {
		$this->categoryMapper = $categoryMapper;
	}

	/**
	 * Get category mapper
	 *
	 * @return null
	 */
	private function getCategoryMapper() {
		return $this->categoryMapper;
	}
}