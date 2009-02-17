<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/my-folder.php';

class MyFile extends Model {

	function MyFile ($owner = 0) {
		if($owner == 0)
			$this->owner = $_SESSION['id'];
		else
			$this->owner = $owner;
			
		$this->_table = "myFile";
		$this->_index = "id";
		
		parent::Model();
	}

	function getFolderFileList($folder) {
		$fields = array('owner'=>$this->owner,
						'parent'=>$folder,
						);
		return $this->db->getByArray($fields);
	}
	
	function getTree($parent = 0) {
			$this->myFolderHandler = new MyFolder($this->owner);
			$this->getTreeNode($parent);
	}
		
	
	function getTreeNode($parent) {
	
		$folderList = $this->myFolderHandler->getFolderList($parent);
		
		for($x=0; count($folderList) > $x; $x++) {
			$tree[$x] = $folderList;
			$tree[$x]['files'] = $this->getFolderFileList($folderList[$x]['id']);
			$tree[$x]['folders'] = $this->getTreeNode($folderList[$x]['id']);
		}
		
	
		if ($parent == 0) {
			$myFolders[0] = array('id' => '0', 'name' => 'TOP');	
		} else {
			$query = "SELECT * FROM myFolder WHERE owner = " . $_SESSION['id'] . " AND parent = ". (int)$myFolder . " ORDER BY name";
			
			$myFolders = $this->db->getAll($query);
		}
		
		if (is_array($myFolders)) {
			if (!isset($this->depth))
				$this->depth = -1;
			else
				$this->depth++;			
			foreach ($myFolders as $folder) {
				
				if ($folder['name'] != 'TOP') {
					
					$this->t_body->set_var('MYFOLDER_NAME', $folder['name']);
					$this->t_body->set_var('MARGIN_LEFT', $this->depth * 10);
					if ($this->i > 1)
						$this->t_body->parse('my_files_block', 'optgroup_end', true);
					$this->t_body->parse('my_files_block', 'optgroup_start', true);					
					
				}
				
				//$this->listMyFiles($folder['id']);
				
				$query = "SELECT id, description FROM myFile WHERE owner = " . $_SESSION['id'] . " AND parent = " . $folder['id'] . " AND deleted = 0 ORDER BY description";
				
				$myFiles = $this->db->getAll($query);
				if (is_array($myFiles)) {
					$this->depth++;
					foreach ($myFiles as $file) {
						
						$output = array('MYFILE_ID' => $file['id'],
										'MYFILE_NAME' => $file['description'],
										'MARGIN_LEFT' => $this->depth * 10);
										
						$this->t_body->set_var($output);
						$this->t_body->parse('my_files_block', 'my_files', true);
					}
					$this->depth--;
				}
				
				
			}
			$this->depth--;
			$this->t_body->parse('my_files_block', 'optgroup_end', true);
		}
	}
}
?>