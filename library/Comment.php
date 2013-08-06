<?php

class Comment extends DeprecatedModel {
	function Comment () {
		$this->_table = "blogComment";
		$this->_index = "id";
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	function getByID($id) {
		$query = "SELECT c.*, user.title, user.firstname, user.lastname, b.cid
			FROM {$this->_table} c LEFT JOIN user ON c.owner = user.id
			JOIN blog b ON b.id = c.bid
			WHERE c.id = $id AND c.deleted IS NULL
			ORDER BY c.created DESC";
		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getRow($query);
	}

	function getByBID($bid) {
		$query = "SELECT {$this->_table}.*, user.synergy_id, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE bid = $bid AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['comments_size'] > 0 ? "$query LIMIT 0, {$this->_config['comments_size']}" : $query;

		return $this->db->getAll($query);
	}

	function count($bid) {
		$query = "SELECT COUNT(*) AS count FROM {$this->_table} WHERE bid = $bid AND deleted IS NULL";
		return $this->db->getOne($query);
	}

	function create($comment) {
		$comment = array_merge(array(
				'owner'   => $_SESSION['id'],
				'created' => 'NOW()'
			), $comment);

		return parent::create($comment);
	}

	function delete($id) {
		$comment = array(
			'id'      => $id,
			'deleted' => 'NOW()'
		);

		return parent::update($comment);
	}

	function permission($id) {
		$comment = $this->getByID($id);
		return $comment['owner'] == $_SESSION['id'] || $this->group->getPermission($comment['cid']) >= WRITE_ACCESS || NEWS_teacher_edit_other;
	}

	function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}
}

?>