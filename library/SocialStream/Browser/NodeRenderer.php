<?php
/**
 * NodeRenderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Browser;


class NodeRenderer {

	/**
	 * The usual sitepage
	 *
	 * @var null
	 */
	private $page = null;

	/**
	 * The contents of the current node
	 *
	 * @var array
	 */
	private $nodeData = array();

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $nodeData
	 */
	public function __construct($page, $nodeData) {
		$this->setPage($page);
		$this->setNodeData($nodeData);
	}

	/**
	 * Render tree and excrete it
	 */
	public function render() {
		$this->getPage()->setFile(TEMPLATE_content, 'component/homepage/socialstream/node.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'breadcrumb', 'breadcrumb_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'node_category', 'node_category_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'node_file', 'node_file_block');

		$this->getPage()->setVar(array(
			'breadcrumb_block' => '',
			'node_category_block' => '',
			'node_file_block' => ''
		));

		/* Current node is breadcrumbs[0]
		 */
		$this->renderCurrentNode();

		/* Render all the breadcrumbs
		 */
		$this->renderBreadcrumbs();

		/* Render all the records
		 */
		$this->renderContents();

		/* Send it out
		 */
		$this->getPage()->setAjax();
		$this->getPage()->template->pparse('finaloutput', TEMPLATE_content);
	}

	/**
	 * Render the title of the node you're currently in
	 */
	private function renderCurrentNode() {
		$nodeData = $this->getNodeData();

		$currentNode = end(array_values($nodeData['breadcrumbs']));

		$this->getPage()->setVar(array(
			'NODE_NAME' => $currentNode['name']
		));
	}

	/**
	 * Render breadcrumbs for this node
	 */
	private function renderBreadcrumbs() {
		$nodeData = $this->getNodeData();

		$allBreadcrumbs = $nodeData['breadcrumbs'];

		$j = 0;
		foreach($allBreadcrumbs as $currentBreadcrumb) {
			$this->getPage()->setVar(array(
				'BREADCRUMB_CLASS' => (count($allBreadcrumbs) -1 == $j) ? 'last' : '',
				'BREADCRUMB_CATEGORY_ID' => $currentBreadcrumb['categoryId'],
				'BREADCRUMB_NAME' => $currentBreadcrumb['name']
			));

			$this->getPage()->parse('breadcrumb_block', 'breadcrumb', true);
			$j++;
		}
	}

	/**
	 * Render all the contents of the current node
	 */
	private function renderContents() {
		$nodeData = $this->getNodeData();

		$allRecords = $nodeData['records'];

		for($i = 0; $i < count($allRecords); $i++) {
			$currentRecord = $allRecords[$i];

			switch ($currentRecord['type']) {
				case 'category': $this->renderCategory($currentRecord, $i); break;
				case 'file': $this->renderFile($currentRecord, $i); break;
				default: break;
			}
		}
	}

	/**
	 * Render a category record
	 *
	 * @param $currentRecord
	 */
	private function renderCategory($currentRecord, $i) {
		$this->getPage()->setVar(array(
			'NODE_CATEGORY_ON_OFF' => $i % 2 ? '' : 'dark',
			'NODE_CATEGORY_ID' => $currentRecord['categoryId'],
			'NODE_CATEGORY_NAME' => $currentRecord['name'],
		));

		$this->getPage()->parse('node_category_block', 'node_category', true);
	}

	/**
	 * Render a file record
	 *
	 * @param $currentRecord
	 */
	private function renderFile($currentRecord, $i) {
		$this->getPage()->setVar(array(
			'NODE_FILE_ON_OFF' => $i % 2 ? '' : 'dark',
			'NODE_FILE_ID' => $currentRecord['fileId'],
			'NODE_FILE_NAME' => $currentRecord['name'],
			'NODE_FILE_ICON' => $currentRecord['icon'],
		));

		$this->getPage()->parse('node_file_block', 'node_file', true);
	}

	/**
	 * Set page
	 *
	 * @param null $page
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

	/**
	 * Set node data
	 *
	 * @param array $nodeData
	 */
	private function setNodeData($nodeData) {
		$this->nodeData = $nodeData;
	}

	/**
	 * Get node data
	 *
	 * @return array
	 */
	private function getNodeData() {
		return $this->nodeData;
	}
}
