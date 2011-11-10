<?php

class Eportfolio_Blog_Item extends DeprecatedModel {
	public $_table = "eportfolio_blog";
	public $_index = "id";

	/**
	 * Custom constructor
	 */
	public function __construct() {
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	/**
	 * Gets a specific blog entry.
	 *
	 * @param  $id
	 * @return array|bool
	 */
	public function getByID($id) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE {$this->_table}.id = $id AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";

		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getRow($query);
	}

	/**
	 * Gets all the blogs authored by $ownerID
	 *
	 * @param  $ownerID
	 * @return array|bool
	 */
	public function getByOwnerID($ownerID) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE owner = $ownerID AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";

		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

	/**
	 * Creates a blog record.
	 *
	 * @param  $blog
	 * @return int
	 */
	public function create($blog) {
		$blog = array_merge(array(
			'owner' => $_SESSION['id'],
			'created' => 'NOW()'
		), $blog);

		return parent::create($blog);
	}

	/**
	 * Marks a blog as being deleted.
	 *
	 * @param  $id
	 * @return bool
	 */
	public function delete($id) {
		$blog = array(
			'id' => $id,
			'deleted' => 'NOW()'
		);

		return parent::update($blog);
	}

	/**
	 * @param  $id
	 * @return bool
	 */
	public function permission($id) {
		$blog = $this->getByID($id);

		if (is_array($blog)) {
			$permission = $this->group->getPermission($blog['cid']);

			if ($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $permission >= WRITE_ACCESS)) {
				return true;
			}
			else {
				return false;
			}
		}
		else {
			false;
		}
	}

	/**
	 * @param null $config
	 * @return array
	 */
	public function config($config = null) {
		if ($config) {
			$this->_config = $config;
		}

		return $this->_config;
	}

	/**
	 * Search for an item
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
		$keyword = mysql_real_escape_string($keyword);

		$query = "SELECT `blog`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
						MATCH(`heading`,`body`) AGAINST ('$keyword') AS `score`
					FROM `blog`
					LEFT JOIN `user` ON `blog`.owner = `user`.id
					LEFT JOIN `perms` ON `blog`.cid = `perms`.cid
						AND `perms`.gid = {$_SESSION['gid']}
						AND `perms`.type > 0
					LEFT JOIN `userAccess` ON `blog`.cid = `userAccess`.sid
						AND `userAccess`.service = 1
						AND `userAccess`.uid = {$_SESSION['id']}
					WHERE (`perms`.cid OR `userAccess`.sid)
						AND MATCH(`heading`,`body`) AGAINST ('$keyword')
						AND `deleted` IS NULL
					ORDER BY `$sort_by` $sort_dir
					LIMIT $offset, $limit;
		";

		$count_query = "SELECT COUNT(*)
						FROM `blog`
						LEFT JOIN `user` ON `blog`.owner = `user`.id
						LEFT JOIN `perms` ON `blog`.cid = `perms`.cid
							AND `perms`.gid = {$_SESSION['gid']}
							AND `perms`.type > 0
						LEFT JOIN `userAccess` ON `blog`.cid = `userAccess`.sid
							AND `userAccess`.service = 1
							AND `userAccess`.uid = {$_SESSION['id']}
						WHERE (`perms`.cid OR `userAccess`.sid)
							AND MATCH(`heading`,`body`) AGAINST ('$keyword')
							AND `deleted` IS NULL
		";

		$count = $this->db->getOne($count_query);

		return $this->db->getAll($query);
	}
}

