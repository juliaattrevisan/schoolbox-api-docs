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
			foreach($this->getAllBlogs() as $currentBlog) {
				print_r($currentBlog);
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
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$this->setAllBlogs($result);
		}
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