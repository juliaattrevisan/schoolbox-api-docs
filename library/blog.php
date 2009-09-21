<?php

require_once dirname(__FILE__) . '/../include/model.php';
require_once dirname(__FILE__) . '/../library/group.php';

class Blog extends Model {
	function Blog () {
		$this->_table = "blog";
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
	
	function getByCID($cid) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE cid = $cid AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;
			
		return $this->db->getAll($query);
	}
	
	function create($blog) {
		$blog = array_merge(array(
			'owner'   => $_SESSION['id'],
			'created' => 'NOW()'
		), $blog);
		
		return parent::create($blog);
	}
	
	function update($blog) {
		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}
	
	function delete($id) {
		$blog = array(
			'id'      => $id,
			'deleted' => 'NOW()'
		);
	
		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}
		
	function permission($id) {
		$blog = $this->getByID($id);
		if(is_array($blog)) {
			$permission = $this->group->getPermission($blog['cid']);
			if($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $permission >= WRITE_ACCESS))
				return true;
			else
				return false;
		}
		else
			false;
	}
	
	function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}
}

?>