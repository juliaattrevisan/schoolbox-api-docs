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
						UNIX_TIMESTAMP(f.submitted) submitted, UNIX_TIMESTAMP(f.modified) modified, 
						ft.name typeName, c.name categoryName, c.id cid, af.description as mimeDescription, iconFile, af.inline
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.id = '$fileID' AND deleted = 0";
		return $this->db->getRow($query);
	}

	function getAllInCategory($categoryID, $config = false) {
	
		require_once dirname(__FILE__) . '/group.php';
		$groupHandler = new Group;

		$query = "SELECT f.*, u.firstname, u.lastname, u.title, 
						UNIX_TIMESTAMP(f.submitted) submitted, UNIX_TIMESTAMP(f.modified) modified,
						ft.name typeName, c.name categoryName, af.description as mimeDescription, iconFile, af.inline
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  LEFT JOIN submission_box sb on sb.fid = f.id
				  WHERE f.category = " . $categoryID . " AND f.deleted = '0' ";
		
		if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS)
			$query .= " AND f.hidden = 0";	


		if (!empty($config['filter']) && $config['filter'][0] != 'all') {
		
			$config['filter'] = is_array($config['filter']) ? $config['filter'] : array($config['filter']);
			
			$query .= " AND ( 1=2 ";
			if (in_array('document', $config['filter']))
				$query .= " OR ( f.mime_type LIKE '%pdf' OR f.mime_type LIKE '%msword' OR f.mime_type LIKE '%officedoc%' OR f.mime_type LIKE '%rtf' OR f.mime_type LIKE 'text%' OR f.mime_type LIKE '%document%' OR f.mime_type LIKE '%powerpoint%') ";
			
			if (in_array('image', $config['filter']))
				$query .= " OR ( f.mime_type LIKE '%image%' ) ";
				
			if (in_array('audio', $config['filter']))
				$query .= " OR ( f.mime_type LIKE '%audio%' ) ";	

			if (in_array('video', $config['filter']))
				$query .= " OR ( f.mime_type LIKE '%video%' OR f.mime_type LIKE '%flash%' OR f.mime_type LIKE '%realmedia%' ) ";	
			
			if (in_array('other', $config['filter']))
				$query .= " OR ( f.mime_type LIKE 'application%' AND f.mime_type NOT LIKE '%pdf' AND f.mime_type NOT LIKE '%msword' AND f.mime_type NOT LIKE '%rtf' AND f.mime_type NOT LIKE 'text%' AND f.mime_type NOT LIKE '%document%' AND f.mime_type NOT LIKE '%powerpoint%') ";

			$query .= ") ";
		}
		
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
		return strcmp(strtolower($data1['description']), strtolower($data2['description']));
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
		$data['modified'] = 'NOW()';	

		if(is_file($file['tmp_name']) && is_readable($file['tmp_name']) ) {
			$data['file_size'] = filesize($file['tmp_name']);
			$data['realname'] = $file['name'];
			$data['mime_type'] = $file['type']; /* should hook up to allowedFile to get mime type properly */
			
			if($fileID = parent::create($data)) {
				$filename = FILE_directory.'/'.$fileID.'.dat';
				@unlink($filename);
				copy($file['tmp_name'], $filename);
			}
		}
		else
			$fileID = parent::create($data);
		
		/* Insert log entry */
		$this->logAction($fileID, 'create');
		
		
		return $fileID;
	}
	
	function update($data, $file = '') {
	
		$data['modified'] = 'NOW()';

		/* If new file is being sent update its meta details and copy it into place */
		if(is_file($file['tmp_name']) && is_readable($file['tmp_name']) ) {
			$data['file_size'] = filesize($file['tmp_name']);
			$data['realname'] = $file['name'];
			$data['mime_type'] = $file['type']; /* should hook up to allowedFile to get mime type properly */
			
			parent::update($data);

			$filename = FILE_directory.'/'.$data['id'].'.dat';
			@unlink($filename);
			copy($file['tmp_name'], $filename);
		}
		else
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

        function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
            $keyword = scrubText($keyword);

            $query = "SELECT `file`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname, `allowedFileTypes`.iconFile,
                            MATCH(`file`.`realname`,`file`.`description`,`file`.`comment`) AGAINST ('$keyword') AS `score`
                        FROM `file`
                        LEFT JOIN `allowedFileTypes` ON `file`.mime_type = `allowedFileTypes`.mime
                        LEFT JOIN `user` ON `file`.owner = `user`.id
                        LEFT JOIN `perms` ON `file`.category = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `file`.category = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`file`.`realname`,`file`.`description`,`file`.`comment`) AGAINST ('$keyword')
                            AND `deleted` = 0 AND `hidden` = FALSE
                        ORDER BY `$sort_by` $sort_dir
                        LIMIT $offset, $limit;
            ";

            $count_query = "SELECT COUNT(*)
                            FROM `file`
                            LEFT JOIN `user` ON `file`.owner = `user`.id
                            LEFT JOIN `perms` ON `file`.category = `perms`.cid
                                AND `perms`.gid = {$_SESSION['gid']}
                                AND `perms`.type > 0
                            LEFT JOIN `userAccess` ON `file`.category = `userAccess`.sid
                                AND `userAccess`.service = 1
                                AND `userAccess`.uid = {$_SESSION['id']}
                            WHERE (`perms`.cid OR `userAccess`.sid)
                                AND MATCH(`realname`,`description`,`comment`) AGAINST ('$keyword')
                                AND `deleted` = 0 AND `hidden` = FALSE
            ";

            $count = $this->db->getOne($count_query);

            return $this->db->getAll($query);
        }
}
?>
