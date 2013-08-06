<?php

class File extends DeprecatedModel {

    function File () {

        $this->_table = "file";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

    function getByID ($fileID) {
        $query = "SELECT f.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName,
						UNIX_TIMESTAMP(f.submitted) submitted, UNIX_TIMESTAMP(f.modified) modified,
						c.name categoryName, c.id cid, af.description as mimeDescription, iconFile, af.inline
				  FROM file f
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.id = '$fileID' AND deleted = 0";
        return $this->db->getRow($query);
    }

    /**
     * @param $categoryID
     * @param array|bool $config
     * @return array|bool
     */
    function getAllInCategory ($categoryID, $config = false) {
        $groupHandler = new Group;

        if(is_array($categoryID)) {
            $cats = " IN ('" . implode("', '", $categoryID) . "') ";
        } else
            $cats = " = '$categoryID' ";

        $query = "SELECT f.*, u.firstname, u.lastname, u.title,
						UNIX_TIMESTAMP(f.submitted) submitted, UNIX_TIMESTAMP(f.modified) modified,
						c.name categoryName, af.description as mimeDescription, iconFile, af.inline
				  FROM file f
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  LEFT JOIN submission_box sb on sb.fid = f.id
				  WHERE f.category $cats AND f.deleted = '0' ";

        if ( $groupHandler->getPermission($categoryID) < WRITE_ACCESS ) {
            $query .= " AND f.hidden = 0";
        }

        if ( !empty( $config['filter'] ) && $config['filter'][0] != 'all' ) {

            $config['filter'] = is_array($config['filter']) ? $config['filter'] : array( $config['filter'] );

            $query .= " AND ( 1=2 ";

            $filter_sql = array(
                'document' => " OR ( f.mime_type LIKE '%pdf%' OR f.mime_type LIKE '%msword' OR f.mime_type LIKE '%officedoc%' OR f.mime_type LIKE '%rtf' OR f.mime_type LIKE 'text%' OR f.mime_type LIKE '%document%' OR f.mime_type LIKE '%powerpoint%') ",
                'image' => " OR ( f.mime_type LIKE '%image%' ) ",
                'audio' => " OR ( f.mime_type LIKE '%audio%' ) ",
                'video' => " OR ( f.mime_type LIKE '%video%' OR f.mime_type LIKE '%flash%' OR f.mime_type LIKE '%realmedia%' ) ",
            );
            $filter_sql['other'] = ' OR NOT (1=2 '.$filter_sql['document'].$filter_sql['image'].$filter_sql['audio'].$filter_sql['video'].')';

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

        $sortFields = array( 'name' => 'f.description', 'date' => 'f.submitted' );

        $config['sort'] = isset( $sortFields[$config['sort']] ) ? $config['sort'] : 'name';
        $config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY " . $sortFields[$config['sort']] . " " . $config['sortdir'];

        if ( $config['size'] > 0 ) {
            $query .= " LIMIT 0, " . $config['size'];
        }
        return $this->db->getAll($query);

    }

    public function migrateCategory ($sourceCategoryId, $destinationCategoryId) {
        $fileList = $this->getAllByField('category', $sourceCategoryId);

        if ( is_array($fileList) ) {
            foreach ( $fileList as &$file ) {
                $sourceFilename = Cfg::get('file_dir') . '/' . $file['id'] . '.dat';

                unset($file['id']);

                $file['category'] = $destinationCategoryId;

                $file['id'] = parent::create($file);
                
                if ( is_readable($sourceFilename) ) {

                    $destinationFilename = Cfg::get('file_dir') . '/' . $file['id'] . '.dat';

                    if ( is_writable( dirname($destinationFilename)) ) {
                        copy($sourceFilename, $destinationFilename);
                    }
                    else {
                        $this->db->delete($this->_table, $this->_index, $file['id']);
                    }

                }
            }
        }
    }

    function mergeLinks ($fileList, $linkList) {
        $fileList = is_array($fileList) ? $fileList : array();
        $linkList = is_array($linkList) ? $linkList : array();

        $fileList = array_merge($fileList, $linkList);
        usort($fileList, array( "File", "sortResult" ));
        return $fileList;
    }

    function sortResult ($data1, $data2) {
        return strcmp(strtolower($data1['description']), strtolower($data2['description']));
    }

    function logAction ($fileID, $action) {
        $logEntry = array(
            'object' => 'file', 'objectId' => $fileID, 'action' => $action, 'user' => $_SESSION['id'],
        );

        $this->db->insert('cmsLog', $logEntry);
    }

    function create ($data, $file = '') {

        $allowedFileHandler = new AllowedFile();
        $fileDir = Cfg::get('file_dir') . '/';

        // if we can't write into the files directory, don't try to insert into database - throw error instead
        if(!is_writable($fileDir)) {
            error(9, "unable to upload the requested file.<br />Please try again, and if the problem persists please contact your system administrator.");
        }

        $data['submitted'] = 'NOW()';
        $data['modified'] = 'NOW()';

        if ( is_file($file['tmp_name']) && is_readable($file['tmp_name']) ) {
            $data['file_size'] = filesize($file['tmp_name']);
            $data['realname'] = $file['name'];
            $data['mime_type'] = $allowedFileHandler->getMime($allowedFileHandler->getExtension($file['name']));

            // if successfully registered file into database
            if ( $fileID = parent::create($data) ) {
                // set the file to be copied
                $filename = $fileDir . $fileID . '.dat';

                // If the file destination already exists, delete it so we can upload the new file.
                // Ignore any errors, file probably won't exist
                @unlink($filename);

                // Since write permissions were already checked, problems should not occur here, but if they do, let someone know.
                if(!copy($file['tmp_name'], $filename)) {
                    error(9, "unable to upload the requested file.<br />Please contact your system administrator.");
                }
            }
        }
        else {
            $fileID = parent::create($data);
        }

        /* Insert log entry */
        $this->logAction($fileID, 'create');

	    /* If video add to video processing queue */
	    if(stristr($data['mime_type'],'video/')) {
		    $mapper = new Schoolbox\Video\QueueMapper();
	        $videoQ = new Schoolbox\Video\Queue();
		    $videoQ->setFileID($fileID);
		    $videoQ->setStatus( Schoolbox\Video\QueueMapper::STATUS_PENDING );
		    $videoQ->setType( Schoolbox\Video\QueueMapper::FILE_TYPE_SBFS);

		    $mapper->save($videoQ);
	    }

        return $fileID;
    }

    function update ($data, $file = '') {
        $allowedFileHandler = new AllowedFile();

        $fileDir = Cfg::get('file_dir') . '/';

        // if we can't write into the files directory, don't try to insert into database - throw error instead
        if(!is_writable($fileDir)) {
            error(9, "unable to upload the requested file.<br />Please try again, and if the problem persists please contact your system administrator.");
        }

        $data['modified'] = 'NOW()';

        /* If new file is being sent update its meta details and copy it into place */
        if ( is_file($file['tmp_name']) && is_readable($file['tmp_name']) ) {
            $data['file_size'] = filesize($file['tmp_name']);
            $data['realname'] = $file['name'];
            $data['mime_type'] = $allowedFileHandler->getMime($allowedFileHandler->getExtension($file['name']));

            parent::update($data);

            $filename = $fileDir . $data['id'] . '.dat';
            @unlink($filename);

            if(!copy($file['tmp_name'], $filename)) {
                error(9, "unable to upload the requested file.<br />Please contact your system administrator.");
            }
        }
        else {
            parent::update($data);
        }

        /* Update log entry */
        $this->logAction($data[$this->_index], 'modify');
    }

    function delete ($id, $clean = false) {

        if ( $clean ) {
            $videoMapper = new Schoolbox\Video\ResourceVideoMapper();
            try {
                /** @var Schoolbox\Video\ResourceVideo $video */
                $video = $videoMapper->findById($id);
                parent::delete($id);
                $filename = Cfg::get('file_dir') . '/' . $id . '.dat';
                @unlink($filename);

                /* unlink video files */
                @unlink($video->getVideoLocation());
                @unlink($video->getThumbnailLocation());
            }
            catch (Schoolbox\Mapper\Exception $ex) {}
        }
        else {
            /* We just mark files as deleted */
            parent::update(array( 'id' => $id, 'deleted' => 1 ));
        }

        /* Delete log entry */
        $this->logAction($id, 'delete');

    }

    function search ($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
        $keyword = mysql_real_escape_string($keyword);

        $query = "SELECT `file`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname, `allowedFileTypes`.iconFile,
                            `perms`.type AS perms_type, `userAccess`.type AS userAccess_type,
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

	/**
	 * Soft-deletes content
	 *
	 * TODO: Refactor
	 *
	 * @param  $cid
	 * @return void
	 */
	public function homepageClean($cid) {
		$query = "UPDATE {$this->_table} SET deleted = 1 WHERE category = '{$cid}'";

		$this->db->query($query);
	}

}

?>
