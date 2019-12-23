<?php

class Blog extends DeprecatedModel {

    /**
	 * @deprecated
	 */
	function __construct() {
		$this->_table = "blog";
		$this->_index = "id";
		$this->_config = array();

		parent::__construct();
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
                        JOIN `folder` ON `folder`.id = `blog`.folder_id AND `folder`.deleted_At IS NULL
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
                            JOIN `folder` ON `folder`.id = `blog`.folder_id AND `folder`.deleted_At IS NULL
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
}
