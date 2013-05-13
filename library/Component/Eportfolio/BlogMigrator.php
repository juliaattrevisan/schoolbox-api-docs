<?php
/**
 * BlogMigrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

class BlogMigrator extends MigratorAbstract {

	/**
	 * All blogs
	 *
	 * @var null
	 */
	private $allBlogs = array();

	/**
	 * Setup
	 */
	protected function setup() {
		$this->fetchAll();
	}

	/**
	 * Migrate this component
	 */
	public function migrate() {
		if (count($this->getAllBlogs())) {
			$newComp = new \Component_Instance();
			$newComp->definitionId = 77;
			$newComp->identifier = $this->getPageId();
			$newComp->module = 'eportfolio';
			$newComp->sequence = 0;
			$newComp->position = 'left';

			$newCompIds = \Component_InstanceMapper::getInstance()->save($newComp);

			foreach($this->getAllBlogs() as $currentBlog) {
				$newBlog = new \Component\Eportfolio\Blog();
				$newBlog->setPageId($this->getPageId());
				$newBlog->setUid($this->getUserId());
				$newBlog->setTitle($currentBlog['heading']);
				$newBlog->setContent($currentBlog['body']);
				$newBlog->setCreatedAt($currentBlog['created']);
				$newBlog->setUpdatedAt($currentBlog['created']);

				$id = \Component\Eportfolio\BlogMapper::getInstance()->save($newBlog);

				if (is_array($id) && count($id)) {
					$allComments = $this->fetchAllComments($currentBlog['id']);

					/* Migrate blog comments
					 */
					if (count($allComments)) {
						$blogId = array_pop($id);

						foreach($allComments as $currentComment) {
							$newComment = new \Component\Eportfolio\BlogComment();
							$newComment->setBlogId($blogId);
							$newComment->setUid($currentComment['owner']);
							$newComment->setContent($currentComment['body']);
							$newComment->setCreatedAt($currentComment['created']);
							$newComment->setUpdatedAt($currentComment['created']);

							\Component\Eportfolio\BlogCommentMapper::getInstance()->save($newComment);
						}
					}
				}
			}
		}
	}

	/**
	 * Fetch all the records for this component
	 */
	protected function fetchAll() {
		$query = "
			SELECT *
			FROM eportfolio_blog
			WHERE owner = {$this->getUserId()}
			AND deleted IS NULL
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$this->setAllBlogs($result);
		}
	}

	/**
	 * Fetch all the records for this component
	 *
	 * @param $blogId
	 * @return array
	 */
	protected function fetchAllComments($blogId) {
		$query = "
			SELECT *
			FROM eportfolio_blogComment
			WHERE bid = {$blogId}
			AND deleted IS NULL
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			return $result;
		}

		return array();
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $allBlogs
	 */
	private function setAllBlogs($allBlogs) {
		$this->allBlogs = $allBlogs;
	}

	/**
	 * @return null
	 */
	private function getAllBlogs() {
		return $this->allBlogs;
	}
}