<?php

class Eportfolio_Blog_Comment extends DeprecatedModel {
	public $_table = "eportfolio_blogComment";
	public $_index = "id";

	/**
	 * Custom constructor
	 */
	public function __construct() {
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	/**
	 * Get a specific comment
	 *
	 * @param  $commentID
	 * @return array|bool
	 */
	public function getByID($commentID) {
		$query = "SELECT c.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} c
            LEFT JOIN user ON c.owner = user.id
			LEFT JOIN eportfolio_blog b ON b.id = c.bid
			WHERE c.id = $commentID
            AND c.deleted IS NULL
			ORDER BY c.created DESC";

		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getRow($query);
	}

	/**
	 * Get all the comments for a particular blog.
	 *
	 * @param  $bid
	 * @return array|bool
	 */
	public function getByBID($bid) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE bid = $bid AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";

		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getAll($query);
	}

	/**
	 * @param  $bid
	 * @return
	 */
	public function count($bid) {
		$query = "SELECT COUNT(*) AS count FROM {$this->_table} WHERE bid = $bid AND deleted IS NULL";

		return $this->db->getOne($query);
	}

	/**
	 * Create comment
	 *
	 * @param  $comment
	 * @return int
	 */
	public function create($comment) {
		$comment = array_merge(array(
			'owner' => $_SESSION['id'],
			'created' => 'NOW()'
		), $comment);

		return parent::create($comment);
	}

	/**
	 * Delete comment
	 *
	 * @param  $id
	 * @return bool
	 */
	public function delete($id) {
		$comment = array(
			'id' => $id,
			'deleted' => 'NOW()'
		);

		return parent::update($comment);
	}

	/**
	 * @param  $id
	 * @return bool
	 */
	public function permission($id) {
		$comment = $this->getByID($id);
		return $comment['owner'] == $_SESSION['id'] || $this->group->getPermission($comment['cid']) >= WRITE_ACCESS || NEWS_teacher_edit_other;
	}

	/**
	 * @param null $config
	 * @return array
	 */
	public function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}
}
