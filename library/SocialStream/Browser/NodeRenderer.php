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
		$this->getPage()->setAjax();
		$this->getPage()->template->pparse('finaloutput', TEMPLATE_content);
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