<?php

class Blog extends DeprecatedModel {

	/**
	 * @deprecated
	 */
	function Blog () {
		$this->_table = "blog";
		$this->_index = "id";
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	/**
	 * @deprecated use \Schoolbox\Blog\BlogMapper::findById()
	 */
	function getByID($id) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, user.synergy_id
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner_id = user.id
			WHERE {$this->_table}.id = $id AND deleted_at IS NULL
			ORDER BY {$this->_table}.created_at DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getRow($query);
	}

    /**
     * Get all the blogs for a category.
     *
     * @deprecated use \Schoolbox\Blog\BlogMapper::findByField('cid')
     * @param <type> $folderId
     * @return <type>
     */
	function getByCID($folderId) {
		$query = "SELECT {$this->_table}.*, user.synergy_id, user.title, user.firstname, user.lastname, created_at, UNIX_TIMESTAMP(created_at) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner_id = user.id
			WHERE folder_id = $folderId AND deleted_at IS NULL
			ORDER BY {$this->_table}.created_at DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

    /**
     * Get all the blogs for a category in a particular year.
     *
     * @deprecated use \Schoolbox\Blog\BlogMapper::findByField('cid')->filterBy()
     * @param <type> $folderId
     * @return <type>
     */
	function getByCIDAndYear($folderId, $year) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, created_at, UNIX_TIMESTAMP(created_at) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner_id = user.id
			WHERE folder_id = $folderId
            AND deleted_at IS NULL
            AND created_at LIKE '$year%'
			ORDER BY {$this->_table}.created_at DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

	/**
	 * @deprecated use \Schoolbox\Blog\BlogMapper::findByField('cid')->filterBy()
	 * @param $folderId
	 * @param $year
	 * @param $month
	 * @return array|bool
	 */
	public function getByYearAndMonth ($folderId, $year, $month) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, created_at, UNIX_TIMESTAMP(created_at) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner_id = user.id
			WHERE folder_id = $folderId AND deleted_at IS NULL
				AND CAST( SUBSTRING(`created_at`, 1, 4) AS SIGNED) = {$year}
				AND CAST( SUBSTRING(`created_at`, 6, 2) AS SIGNED) = {$month}
			ORDER BY {$this->_table}.created_at DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

	/**
	 * Returns a list of years and months which have blog posts
	 *
	 * deprecated use \Schoolbox\Blog\BlogMapper::getOccupiedDateList()
	 * @param Integer $categoryId
	 * @return Array|Bool
	 */
	public function getOccupiedDateList ($folderId) {
		$query = "SELECT YEAR(`created_at`) as 'year', `created_at`, MONTH(`created_at`) as 'month'
				  FROM `blog`
				  WHERE `folder_id` = '$folderId'
				  GROUP BY `year` DESC, `month` DESC";

		return $this->db->getAll($query);
	}

	/**
	 * @deprecated use \Schoolbox\Blog\BlogMapper::save()
	 * @param $blog
	 * @return int|string
	 */
	function create($blog) {
		$blog = array_merge(array(
			'owner_id'   => $_SESSION['id'],
			'created_at' => 'NOW()'
		), $blog);

		return parent::create($blog);
	}

	/**
	 * @deprecated use \Schoolbox\Blog\BlogMapper::save()
	 * @param $blog
	 * @return bool
	 */
	function update($blog) {
		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}

	/**
	 * @deprecated use \Schoolbox\Blog\BlogMapper::delete()
	 * @param $id
	 * @return bool
	 */
	function delete($id) {
		$blog = array(
			'id'      => $id,
			'deleted_at' => 'NOW()'
		);

		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}

	/**
	 * @deprecated (why does this event exist?)
	 * @param $id
	 * @return bool
	 */
	function permission($id) {
		$blog = $this->getByID($id);
		if(is_array($blog)) {
			$permission = $this->group->getPermission($blog['folder_id']);
			if($blog['owner_id'] == $_SESSION['id'] || $permission >= WRITE_ACCESS)
				return true;
			else
				return false;
		}
		else
			false;
	}

	/**
	 * @deprecated (...what?)
	 * @param null $config
	 * @return null
	 */
	function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}

	/**
	 *
	 * @param $keyword
	 * @param int $offset
	 * @param int $limit
	 * @param null $count
	 * @param string $sort_by
	 * @param string $sort_dir
	 * @return array|bool
	 */
	function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
            $keyword = $this->db->quote($keyword);

            $query = "SELECT `blog`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                            MATCH(`heading`,`body`) AGAINST ('$keyword') AS `score`
                        FROM `blog`
                        LEFT JOIN `user` ON `blog`.owner_id = `user`.id
                        LEFT JOIN `perms` ON `blog`.folder_id = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `blog`.folder_id = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                            AND `blog`.`deleted_at` IS NULL
                        ORDER BY `$sort_by` $sort_dir
                        LIMIT $offset, $limit;
            ";

            $count_query = "SELECT COUNT(*)
                            FROM `blog`
                            LEFT JOIN `user` ON `blog`.owner_id = `user`.id
                            LEFT JOIN `perms` ON `blog`.folder_id = `perms`.cid
                                AND `perms`.gid = {$_SESSION['gid']}
                                AND `perms`.type > 0
                            LEFT JOIN `userAccess` ON `blog`.folder_id = `userAccess`.sid
                                AND `userAccess`.service = 1
                                AND `userAccess`.uid = {$_SESSION['id']}
                            WHERE (`perms`.cid OR `userAccess`.sid)
                                AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                                AND `blog`.`deleted_at` IS NULL
            ";

            $count = $this->db->getOne($count_query);

            return $this->db->getAll($query);
        }

	/**
	 * Soft-deletes content
	 *
	 * TODO: Refactor
	 *
	 * @deprecated use \Schoolbox\Blog\BlogMapper::delete()
	 * @param  $folderId
	 * @return void
	 */
	public function homepageClean($folderId) {
		$query = "UPDATE {$this->_table} SET deleted_at = NOW() WHERE folder_id = '{$folderId}'";

		$this->db->query($query);
	}
}

