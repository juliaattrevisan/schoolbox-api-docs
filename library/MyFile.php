<?php

class MyFile extends DeprecatedModel {

	function MyFile ($owner = 0) {
		if($owner == 0)
			$this->owner = $_SESSION['id'];
		else
			$this->owner = $owner;

		$this->_table = "myFile";
		$this->_index = "id";

		parent::DeprecatedModel();
	}

    /**
     * Gets all the published files for the specified user.
     *
     * @return <type>
     */
    function getAllPublished($config = false) {
        $query = "SELECT f.*, UNIX_TIMESTAMP(f.submitted) submitted, UNIX_TIMESTAMP(f.published) published, af.iconFile FROM ".$this->_table." f
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE owner = " . $this->owner . " AND published IS NOT NULL AND deleted = 0";

        if ($config) {
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

            $query .= " ORDER BY $config[sort] $config[sortdir]";
        }

        return $this->db->getAll($query);
    }

	function getAllInFolder($folderID, $config = false) {
		$query = "SELECT f.*, f.submitted, af.iconFile FROM ".$this->_table." f
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE owner = " . $this->owner . " AND parent = ".(int)$folderID." AND deleted = 0";

        if($config['published'] == true) {
            $query .= " AND published IS NOT NULL";
        }

        if ( !empty( $config['filter'] ) && $config['filter'][0] != 'all' ) {

            $config['filter'] = is_array($config['filter']) ? $config['filter'] : array( $config['filter'] );

            $query .= " AND ( 1=2 ";

            $filter_sql = array(
                'document' => " OR ( f.mime_type LIKE '%pdf%' OR f.mime_type LIKE '%msword' OR f.mime_type LIKE '%officedoc%' OR f.mime_type LIKE '%rtf' OR f.mime_type LIKE 'text%' OR f.mime_type LIKE '%document%' OR f.mime_type LIKE '%powerpoint%') ",
                'image' => " OR ( f.mime_type LIKE '%image%' ) ", 'audio' => " OR ( f.mime_type LIKE '%audio%' ) ",
                'video' => " OR ( f.mime_type LIKE '%video%' OR f.mime_type LIKE '%flash%' OR f.mime_type LIKE '%realmedia%' ) ",
                'other' => " OR ( f.mime_type LIKE 'application%' AND f.mime_type NOT LIKE '%pdf' AND f.mime_type NOT LIKE '%msword' AND f.mime_type NOT LIKE '%rtf' AND f.mime_type NOT LIKE 'text%' AND f.mime_type NOT LIKE '%document%' AND f.mime_type NOT LIKE '%powerpoint%') "
            );

            foreach ( $config['filter'] as $filter ) {
                if ( array_key_exists($filter, $filter_sql) ) {
                    $query .= $filter_sql[$filter];
                }
                else {
                    $query .= " OR ( f.mime_type = '$filter ' ) ";
                }
            }

            $query .= ") ";
        }

		return $this->db->getAll($query);
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

		$total = count($folderList);
		for($i=0; $i < $total; $i++) {
			$tree[$i]['files'] = $this->getFolderFileList($folderList[$x]['id']);
			$tree[$i]['folders'] = $this->getTreeNode($folderList[$x]['id']);
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

	function logAction ($fileID, $action) {
		$logEntry = array(	'object'	=> 'myfile',
							'objectId'	=> $fileID,
							'action'	=> $action,
							'user'		=> $_SESSION['id'],
							);

		$this->db->insert('cmsLog', $logEntry);
	}

	function create	($data, $file) {

		$data['submitted']	= 'NOW()';
		$data['owner']	= $this->owner;

		if(is_file($file['tmp_name']) && is_readable($file['tmp_name']) ) {
			$data['file_size'] = filesize($file['tmp_name']);
			$data['realname'] = $file['name'];
			$data['mime_type'] = $file['type']; /* should hook up to allowedFile to get mime type properly */

			if($fileID = parent::create($data)) {
				$filename = Cfg::get('myfile_dir').'/'.$fileID.'.dat';
				@unlink($filename);
				rename($file['tmp_name'], $filename);
			}
		}
		else
			$fileID = parent::create($data);

		$this->logAction($fileID, 'create');

		return $fileID;
	}

	function update ($data) {
		$this->logAction($data['id'], 'modify');
		return parent::update($data);
	}

	function delete ($fileID) {
		$this->logAction($fileID, "delete");
		$data = array('id'		=> $fileID,
					  'deleted'	=> 1);

		return parent::update($data);
	}

	function deleteByParent ($parentID) {
		$fileList = $this->getAllInFolder($parentID);
		if (is_array($fileList)) {
			foreach ($fileList as $file) {
				$this->delete($file['id']);
			}
		}
	}
}
?>