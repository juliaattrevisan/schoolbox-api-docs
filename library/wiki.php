<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Wiki extends Model {
	function Wiki() {
		$this->_table = 'wiki';
		$this->_index = 'wikiID';
		
		parent::Model();
	}
	
	function getByID($wikiID, $revisionID = 0) {
		$query = "SELECT r.*, w.categoryID, w.visible, w.closed, UNIX_TIMESTAMP(w.created) created, UNIX_TIMESTAMP(w.created) modified, 
				  u.title, u.firstname, u.lastname
				  FROM revision r
				  LEFT JOIN ".$this->_table." w ON w.wikiID = r.wikiID
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE r.wikiID = $wikiID ";
		if ($revisionID > 0 && is_numeric($revisionID))
			$query .= "AND r.revisionID = $revisionID ";
			
		$query .= "ORDER BY r.created DESC LIMIT 1";
		return $this->db->getRow($query);
	}
	
	function getRevisionList($wikiID) {
		$query = "SELECT r.*, UNIX_TIMESTAMP(r.created) created, u.title, u.firstname, u.lastname
				  FROM revision r 
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE wikiID = $wikiID
				  ORDER BY r.created DESC";
		return $this->db->getAll($query);
	}
	
	function getByCategory($categoryID) {
		$query = "SELECT r.*, UNIX_TIMESTAMP(r.created) modified, u.title, u.firstname, u.lastname
				  FROM revision r
				  LEFT JOIN ".$this->_table." w ON w.wikiID = r.wikiID
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE categoryID = ".(int)$categoryID." "; 
			
		$query .= "GROUP BY r.wikiID ORDER BY r.created DESC, w.created DESC";
		
		return $this->db->getAll($query);
	}

	function createRevision ($data) {
		$data['created'] = 'NOW()';
		$data['userID'] = $_SESSION['id'];
		return $this->db->insert('revision', $data);
	}
	
	function create($data) {
		$data['created'] = 'NOW()';
		return parent::create($data);
	}

}
?>