<?php

class Comment extends DeprecatedModel {
	function Comment () {
		$this->_table = "blog_comment";
		$this->_index = "id";
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	function getByID($id) {
		$query = "SELECT c.*, user.title, user.firstname, user.lastname, b.folder_id
			FROM {$this->_table} c LEFT JOIN user ON c.owner_id = user.id
			JOIN blog b ON b.id = c.blog_id
			WHERE c.id = $id AND c.deleted_at IS NULL
			ORDER BY c.created_at DESC";
		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getRow($query);
	}

	function getByBID($blog_id) {
		$query = "SELECT {$this->_table}.*, user.synergy_id, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner_id = user.id
			WHERE blog_id = $blog_id AND deleted_at IS NULL
			ORDER BY {$this->_table}.created_at DESC";
		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getAll($query);
	}

	function count($blog_id) {
		$query = "SELECT COUNT(*) AS count FROM {$this->_table} WHERE blog_id = $blog_id AND deleted_at IS NULL";
		return $this->db->getOne($query);
	}

	function create($comment) {
		$comment = array_merge(array(
				'owner_id'   => $_SESSION['id'],
				'created_at' => 'NOW()'
			), $comment);

		return parent::create($comment);
	}

	function delete($id) {
		$comment = array(
			'id'      => $id,
			'deleted_at' => 'NOW()'
		);

		return parent::update($comment);
	}

	function permission($id) {
		$comment = $this->getByID($id);
		return $comment['owner_id'] == $_SESSION['id'] || $this->group->getPermission($comment['folder_id']) >= WRITE_ACCESS;
	}

	function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}
}

?>