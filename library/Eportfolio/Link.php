<?php

class Eportfolio_Link extends DeprecatedModel {
	public $_table = 'eportfolio_link';
	public $_index = 'id';

	/**
	 * Get a link by its id
	 *
	 * @param  $linkID
	 * @return array|bool
	 */
	public function getByID($linkID) {
		$query = "SELECT l.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName,
						UNIX_TIMESTAMP(l.submitted) submitted
				  FROM eportfolio_link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE l.id = $linkID";

		return $this->db->getRow($query);
	}

	/**
	 * Gets all the links owned by $userID
	 *
	 * @param  $userID
	 * @param bool $config
	 * @return array|bool
	 */
	public function getAllInUser($userID, $config = false) {
		$query = "SELECT l.*, u.firstname, u.lastname, u.title, UNIX_TIMESTAMP(submitted) submitted, 'html_alt1' iconFile
				  FROM eportfolio_link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE owner = '$userID'";

		$sortFields = array('Name' => 'l.description', 'Date' => 'l.submitted');
		$config['sort'] = isset($sortFields[$config['sort']]) ? $config['sort'] : 'Name';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $sortFields[$config['sort']] . " " . $config['sortdir'];

		if ($config['size'] > 0) {
			$query .= " LIMIT 0, " . $config['size'];
        }

		return $this->db->getAll($query);
	}

	/**
	 * Append protocol
	 *
	 * @param  $url
	 * @return string
	 */
	public function appendHTTP($url) {
		if (strncasecmp($url, "http://", 7) && strncasecmp($url, "https://", 8)) {
			$url = "http://" . $url;
		}

		return $url;
	}

	/**
	 * Log somethign that changed
	 *
	 * @param  $linkID
	 * @param  $action
	 * @return void
	 */
	public function logAction($linkID, $action) {
		$logEntry = array(
			'object' => 'link',
			'objectId' => $linkID,
			'action' => $action,
			'user' => $_SESSION['id'],
		);

		$this->db->insert('cmsLog', $logEntry);
	}

	/**
	 * Update link
	 *
	 * @param  $data
	 * @return void
	 */
	public function update($data) {
		if (isset($data['url'])) {
			$data['url'] = $this->appendHTTP(trim($data['url']));
		}

		parent::update($data);

		/* Update log entry
		 */
		$this->logAction($data[$this->_index], 'modify');
	}

	/**
	 * Create link
	 *
	 * @param  $data
	 * @return int
	 */
	public function create($data) {
		$data['submitted'] = 'NOW()';
		$data['url'] = $this->appendHTTP(trim($data['url']));

		$linkID = parent::create($data);

		/* Insert log entry
		 */
		$this->logAction($linkID, 'create');

		return $linkID;
	}

	/**
	 * Delete link
	 *
	 * @param  $id
	 * @return void
	 */
	public function delete($id) {
		parent::delete($id);

		/* Delete log entry
		 */
		$this->logAction($id, 'delete');
	}

	/**
	 * Search
	 *
	 * @param  $keyword
	 * @param int $offset
	 * @param int $limit
	 * @param null $count
	 * @param string $sort_by
	 * @param string $sort_dir
	 * @return array|bool
	 */
	public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
		$keyword = Scrub::text($keyword);

		$query = "SELECT `link`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
						MATCH(`url`,`description`,`comment`) AGAINST ('$keyword') AS `score`
					FROM `link`
					LEFT JOIN `user` ON `link`.owner = `user`.id
					LEFT JOIN `perms` ON `link`.category = `perms`.cid
						AND `perms`.gid = {$_SESSION['gid']}
						AND `perms`.type > 0
					LEFT JOIN `userAccess` ON `link`.category = `userAccess`.sid
						AND `userAccess`.service = 1
						AND `userAccess`.uid = {$_SESSION['id']}
					WHERE (`perms`.cid OR `userAccess`.sid)
						AND MATCH(`url`,`description`,`comment`) AGAINST ('$keyword')
						AND `hidden` = FALSE
					ORDER BY `$sort_by` $sort_dir
					LIMIT $offset, $limit;
		";

		$count_query = "SELECT COUNT(*)
						FROM `link`
						LEFT JOIN `user` ON `link`.owner = `user`.id
						LEFT JOIN `perms` ON `link`.category = `perms`.cid
							AND `perms`.gid = {$_SESSION['gid']}
							AND `perms`.type > 0
						LEFT JOIN `userAccess` ON `link`.category = `userAccess`.sid
							AND `userAccess`.service = 1
							AND `userAccess`.uid = {$_SESSION['id']}
						WHERE (`perms`.cid OR `userAccess`.sid)
							AND MATCH(`url`,`description`,`comment`) AGAINST ('$keyword')
							AND `hidden` = FALSE
		";

		$count = $this->db->getOne($count_query);

		return $this->db->getAll($query);
	}
}
