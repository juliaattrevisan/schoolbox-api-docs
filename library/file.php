<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class File extends Model {

	function File () {
	
		$this->_table = "file";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getByID($fileID) {
		$query = "SELECT f.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName,
						UNIX_TIMESTAMP(f.submitted) submitted, ft.name typeName, c.name categoryName, c.id cid, af.description as mimeDescription, iconFile, af.inline
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.id = $fileID AND deleted = 0";
		return $this->db->getRow($query);
	}

	function getAllInCategory($categoryID, $config = false) {
	
		require_once dirname(__FILE__) . '/group.php';
		$groupHandler = new Group;

		$query = "SELECT f.*, u.firstname, u.lastname, u.title, 
						UNIX_TIMESTAMP(f.submitted) submitted, ft.name typeName, c.name categoryName, af.description as mimeDescription, iconFile, af.inline
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  LEFT JOIN submission_box sb on sb.fid = f.id
				  WHERE f.category = " . $categoryID . " AND f.deleted = '0' ";
		
		if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS)
			$query .= " AND f.hidden = 0";	
		
		if ($config['filter'] == 'document')
				$query .= " AND ( f.mime_type LIKE '%pdf' OR f.mime_type LIKE '%msword' OR f.mime_type LIKE '%rtf' OR f.mime_type LIKE 'text%') ";	
						  
		
		$sortFields = array('Name' => 'f.description', 'Date' => 'f.submitted');
		
		$config['sort'] = isset($sortFields[$config['sort']]) ? $config['sort'] : 'Name';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $sortFields[$config['sort']] . " " . $config['sortdir'];
		
		if($config['size'] > 0) 
			$query .= " LIMIT 0, " . $config['size']; 

		return $this->db->getAll($query);
	
	}
	
	function mergeLinks($fileList, $linkList) {
		$fileList = is_array($fileList) ? $fileList : array();
		$linkList = is_array($linkList) ? $linkList : array();
		
		$fileList = array_merge($fileList, $linkList);
		usort($fileList, array("File", "sortResult"));
		return $fileList;
	}
	
	function sortResult($data1, $data2) {
		return strcmp($data1['description'], $data2['description']);
	}
	
	function logAction ($fileID, $action) {
		$logEntry = array(	'object'	=> 'file',
							'objectId'	=> $fileID,
							'action'	=> $action,
							'user'		=> $_SESSION['id'],
							);
		
		$this->db->insert('cmsLog', $logEntry);
	}
	
	function create($data, $file = '') {
				
		$data['submitted'] = 'NOW()';		

		if(is_file($file) && is_readable($file) ) {
			$data['file_size'] = filesize($file);
			$data['realname'] = $file['name'];
			$data['mime_type'] = $file['type']; /* should hook up to allowedFile to get mime type properly */
			
			if($fileID = parent::create($data)) {
				$filename = FILE_directory.'/'.$fileID.'.dat';
				@unlink($filename);
				rename($file, $filename);
			}
		}
		else
			$fileID = parent::create($data);
		
		/* Insert log entry */
		$this->logAction($fileID, 'create');
		
		
		return $fileID;
	}
	
	function update($data) {
		
		parent::update($data);
		
		/* Update log entry */
		$this->logAction($data[$this->_index], 'modify');
	}
	
	function delete($id, $clean = false) {
		
		if($clean) {
			parent::delete($id);
			$filename = FILE_directory.'/'.$id.'.dat';
			@unlink($filename);
		}
		else {
			/* We just mark files as deleted */		
			parent::update(array('id'=>$id, 'deleted'=>1));
		}

		/* Delete log entry */
		$this->logAction($id, 'delete');
		

	}
}	
?>
