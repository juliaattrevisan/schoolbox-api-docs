<?php

require_once dirname(__FILE__) . '/../include/model.php';
require_once dirname(__FILE__) . '/../library/group.php';

class Comment extends Model {
	function Comment () {
		$this->_table = "blog_comment";
		$this->_index = "id";
		$this->_config = array();
		
		$this->group = new Group();
		
		parent::Model();
	}
	
	function getByID($id) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE {$this->_table}.id = $id AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;
		
		return $this->db->getRow($query);
	}

	function getByBID($bid, &$count = false) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE bid = $bid AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;
		
		$count = $this->db->getRow("SELECT COUNT(*) AS count FROM {$this->_table} WHERE bid = $bid AND deleted IS NULL");
		
		return $this->db->getAll($query);
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
	
		return $this->permission($comment['id']) ? parent::update($comment) : false;
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