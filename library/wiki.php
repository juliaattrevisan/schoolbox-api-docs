<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class Wiki extends Model {
	function Wiki() {
		$this->_table = 'wiki';
		$this->_index = 'wikiID';
		$this->_revisionTable = 'wikiRevision';
		
		parent::Model();
	}
	
	function getByID($wikiID, $revisionID = 0) {
		$query = "SELECT r.*, w.categoryID, w.locked, UNIX_TIMESTAMP(w.created) created, UNIX_TIMESTAMP(r.created) modified, 
				  u.title, u.firstname, u.lastname, r.userID authorID
				  FROM ".$this->_revisionTable." r
				  LEFT JOIN ".$this->_table." w ON w.wikiID = r.wikiID
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE w.deleted = 'false' AND r.wikiID = $wikiID ";
		if ($revisionID > 0 && is_numeric($revisionID))
			$query .= "AND r.revisionID = $revisionID ";
			
		$query .= "ORDER BY r.created DESC LIMIT 1";
		return $this->db->getRow($query);
	}
	
	function getRevisionList($wikiID) {
		$query = "SELECT r.*, UNIX_TIMESTAMP(r.created) created, u.title, u.firstname, u.lastname
				  FROM ".$this->_revisionTable." r 
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE wikiID = $wikiID
				  ORDER BY r.created DESC";
		return $this->db->getAll($query);
	}
	
	function getByCategory($categoryID) {
		$query = "SELECT r.*, w.locked, UNIX_TIMESTAMP(r.created) modified, u.title, u.firstname, u.lastname
				  FROM wiki w
				  LEFT JOIN ".$this->_revisionTable." r ON r.revisionID = (SELECT r.revisionID FROM ".$this->_revisionTable." r WHERE r.wikiID = w.wikiID ORDER BY r.created DESC LIMIT 1) 
				  LEFT JOIN user u ON u.id = r.userID
				  WHERE w.deleted = 'false' AND categoryID = ".(int)$categoryID." "; 
			
		$query .= " ORDER BY r.created DESC, w.created DESC";
		
		return $this->db->getAll($query);
	}

	function homepageClean($cid) {
		$query = "DELETE w, r FROM  `".$this->_table."` w, ".$this->_revisionTable." r  WHERE w.wikiID=r.wikiID AND categoryID='$cid'";
		$this->db->query($query);
	}
	
	function createRevision ($data) {
		$data['created'] = 'NOW()';
		$data['userID'] = $_SESSION['id'];
		return $this->db->insert($this->_revisionTable, $data);
	}
	
	function create($data) {
		$data['created'] = 'NOW()';

		$revision = array( 'heading'	=> $data['heading'],
							'body'		=> $data['body'],
					  );
		unset($data['heading'], $data['body']);
		
		$wikiID = parent::create($data);
		if($wikiID) {
			$revision['wikiID'] = $wikiID;
			$this->createRevision($revision);
		}
		return $wikiID;
	}
	
	function revert($wikiID, $revisionID) {
		$wiki = $this->getByID($wikiID, $revisionID);
		if (!isset($wiki['wikiID']))
			return false;
			
		$data = array('wikiID'	=> $wiki['wikiID'],
					  'heading'	=> $wiki['heading'],
					  'body'	=> $wiki['body']);
		return $this->createRevision($data);
	}

	function delete ($wikiID) {
		$data = array('wikiID'	=> $wikiID,
					  'deleted'	=> 'true');
		return parent::update($data);
	}

}
?>