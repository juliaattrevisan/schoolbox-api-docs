<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class MyFolder extends Model {

	function MyFolder ($owner = 0) {
		if($owner == 0)
			$this->owner = $_SESSION['id'];
		else
			$this->owner = $owner;
			
		$this->_table = "myFolder";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getFolderList($parent = 0) {
		$fields = array('owner'=>$this->owner,
						'parent'=>$parent,
						);
		return $this->getByArray($fields);
	
	}
	
	function getParent($id) {
		$query = "SELECT parent FROM ".$this->_table." WHERE id = " . (int)$id;
		return $this->db->getOne($query);
	}

	function getFolderNode($folderID = 0) {
		
		// get folder contents
		$folderList = $this->getFolderList($folderID);

		if ($folderID > 0) {
			// get parent folder contents
			$siblingList = $this->getFolderNode($this->getParent($folderID));
			
			if (is_array($folderList)) {
				// find index of parent node in siblingList
				$total = count($siblingList);
				for ($i = 0; $i < $total; $i++)
					if ($siblingList[$i]['id'] == $folderID) break;
					
				// insert folder contents next to parent node
				array_splice($siblingList, $i+1, 0, $folderList);
			}
			return $siblingList;
		}
		else {
			return $folderList;
		}
	}
	
	function hasChildren($folderID) {
		$query = "SELECT f.id as children 
				  FROM myFolder f
				  WHERE parent = $folderID
				  LIMIT 1";
		
		return $this->db->getOne($query);
	}


}

?>
