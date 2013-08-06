<?php

class Blog extends DeprecatedModel {
	function Blog () {
		$this->_table = "blog";
		$this->_index = "id";
		$this->_config = array();

		$this->group = new Group();

		parent::DeprecatedModel();
	}

	function getByID($id) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, user.synergy_id
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE {$this->_table}.id = $id AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getRow($query);
	}

    /**
     * Get all the blogs for a category.
     *
     * @param <type> $cid
     * @return <type>
     */
	function getByCID($cid) {
		$query = "SELECT {$this->_table}.*, user.synergy_id, user.title, user.firstname, user.lastname, created, UNIX_TIMESTAMP(created) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE cid = $cid AND deleted IS NULL
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

    /**
     * Get all the blogs for a category in a particular year.
     *
     * @param <type> $cid
     * @return <type>
     */
	function getByCIDAndYear($cid, $year) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, created, UNIX_TIMESTAMP(created) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE cid = $cid
            AND deleted IS NULL
            AND created LIKE '$year%'
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

	public function getByYearAndMonth ($cid, $year, $month) {
		$query = "SELECT {$this->_table}.*, user.title, user.firstname, user.lastname, created, UNIX_TIMESTAMP(created) AS createdTimestamp
			FROM {$this->_table} LEFT JOIN user ON {$this->_table}.owner = user.id
			WHERE cid = $cid AND deleted IS NULL
				AND CAST( SUBSTRING(`created`, 1, 4) AS SIGNED) = {$year}
				AND CAST( SUBSTRING(`created`, 6, 2) AS SIGNED) = {$month}
			ORDER BY {$this->_table}.created DESC";
		$query = $this->_config['size'] > 0 ? "$query LIMIT 0, {$this->_config['size']}" : $query;

		return $this->db->getAll($query);
	}

	/**
	 * Returns a list of years and months which have blog posts
	 *
	 * @param Integer $categoryId
	 * @return Array|Bool
	 */
	public function getOccupiedDateList ($categoryId) {
		$query = "SELECT YEAR(`created`) as 'year', `created`, MONTH(`created`) as 'month'
				  FROM `blog`
				  WHERE `cid` = '$categoryId'
				  GROUP BY `year` DESC, `month` DESC";

		return $this->db->getAll($query);
	}

	function create($blog) {
		$blog = array_merge(array(
			'owner'   => $_SESSION['id'],
			'created' => 'NOW()'
		), $blog);

		return parent::create($blog);
	}

	function update($blog) {
		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}

	function delete($id) {
		$blog = array(
			'id'      => $id,
			'deleted' => 'NOW()'
		);

		return $this->permission($blog['id']) ? parent::update($blog) : false;
	}

	function permission($id) {
		$blog = $this->getByID($id);
		if(is_array($blog)) {
			$permission = $this->group->getPermission($blog['cid']);
			if($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $permission >= WRITE_ACCESS))
				return true;
			else
				return false;
		}
		else
			false;
	}

	function config($config = null) {
		if($config)
			$this->_config = $config;
		return $this->_config;
	}

        function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
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

	/**
	 * Soft-deletes content
	 *
	 * TODO: Refactor
	 *
	 * @param  $cid
	 * @return void
	 */
	public function homepageClean($cid) {
		$query = "UPDATE {$this->_table} SET deleted = NOW() WHERE cid = '{$cid}'";

		$this->db->query($query);
	}
}

