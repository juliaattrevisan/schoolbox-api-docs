<?php
/**
 * SocialStream_Thread
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

class Thread {

	/**
	 * A schoolbox collection of SocialStream_Post instances
	 *
	 * @var null
	 */
	private $allPosts = null;

	/**
	 * Resultant tree structure
	 *
	 * @var null
	 */
	private $tree = null;

	/**
	 * Constructor
	 *
	 * @param $homepageId
	 */
	public function __construct($homepageId) {
		$allPosts = \SocialStream\PostMapper::getInstance()->findAllForHomepage($homepageId);

		$this->setAllPosts($allPosts);

		$this->growTree();
	}

	/**
	 * Grow a tree
	 *
	 * @return array
	 */
	public function growTree() {
		$structure = array();

		/* Move everything into an array to start with
		 */
		foreach($this->getAllPosts() as $currentPost) {
			$structure[] = $currentPost;
		}

		/* Push posts into their respective parents
		 */
		foreach($structure as $currentPost) {

			/* Only worry about moving deeper posts
			 */
			if ($currentPost->getParentId()) {
				$destination = $this->findNode($structure, $currentPost->getParentId());

				/* Only try to add it if a destination is found
				 * (This should get around orphaned posts)
				 */
				if ($destination) {
					$destination->getChildren()->add($currentPost);
				}

				/* Remember which ones we can remove from the root level
				 */
				$remove[] = $currentPost->getPostId();
			}
		}

		/* Nullify posts which have been moved elsewhere
		 */
		for ($i = 0; $i < count($structure); $i++) {
			if (in_array($structure[$i]->getPostId(), $remove)) {
				$structure[$i] = null;
			}
		}

		/* Remove posts which were nullified
		 */
		$tree = array_values(array_filter($structure));

		/* Transform it back into a collection, because we normally don't
		 * remove elements from iterator classes
		 */
		$collection = new \Schoolbox\Collection();

		foreach($tree as $node) {
			$collection->add($node);
		}

		/* Save that one for later!
	 	 */
		$this->setTree($collection);

		return $this;
	}

	/**
	 * Find a node in a tree
	 *
	 * @param $tree
	 * @param $id
	 * @return mixed
	 */
	private function findNode($tree, $id) {
		foreach($tree as $leaf) {
			if ($leaf->getPostId() == $id) {
				return $leaf;
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $allPosts
	 */
	private function setAllPosts($allPosts) {
		$this->allPosts = $allPosts;
	}

	/**
	 * @return null
	 */
	private function getAllPosts() {
		return $this->allPosts;
	}

	/**
	 * Set tree
	 *
	 * @param null $tree
	 */
	public function setTree($tree) {
		$this->tree = $tree;
	}

	/**
	 * Get tree
	 *
	 * @return null
	 */
	public function getTree() {
		return $this->tree;
	}
}