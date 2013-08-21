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

			$this->processNode();
		}
	}

	/**
	 * Render it out
	 */
	public function render() {
		header("Content-type: application/json");
		echo json_encode($this->getResult());
	}

	/**
	 * Combine the categories and files into a single list
	 */
	private function processNode() {
		$output = array_merge($this->getCategories(), $this->getFiles());

		$this->setResult($output);
	}

	/**
	 * Get all the subcategories for a particular category
	 *
	 * @return array
	 */
	private function getCategories() {
		$output = array();

		$categoryMapper = new \Schoolbox\Resource\CategoryMapper();

		$allCategories = $categoryMapper->findByParent($this->getNodeId());

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
}