<?php
/**
 * Tree_Category
 *
 * Get a list of categories, their children, and the hierarchy of parent nodes
 * for a selected category
 *
 * Example usage

 	// Show all the nodes
	$tree = new Tree_Category();
	$tree->grow(0);
	print_r($tree->getTree());

    // Get ancestor nodes for category 74
    $tree->setSelectedItem(74);
	print_r($tree->getJsAncestorNodesList());
	print_r($tree->getAncestorNodesList());

 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Tree_Category extends Tree_Abstract {
	public $_table = 'category';
	public $_index = 'id';

	/**
	 * Additional tasks to perform before growing tree
	 *
	 * @abstract
	 */
	protected function preGrow() {
		$this->setHandler(new Category());
	}

	/**
	 * Additional tasks to perform after growing tree
	 *
	 * @abstract
	 */
	protected function postGrow() {
	}
}
