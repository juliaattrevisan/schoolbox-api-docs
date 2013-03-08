<?php

class CategoryNews extends DeprecatedModel {

	function CategoryNews () {

		$this->_table = "subject_news";
		$this->_index = "id";

		parent::DeprecatedModel();
	}

	function getAllForCategory($categoryID, $config = false, $archive = false, $userID = 0) {
        $userID = $userID ? $userID : $_SESSION['id'];
		$query = "SELECT n.id, heading, UNIX_TIMESTAMP(datetime_posted) posted, UNIX_TIMESTAMP(datetime_published) published,
					UNIX_TIMESTAMP(datetime_expires) expires, body, deleted, code, owner, u.firstname, u.lastname, u.title,
					u.id uid, u.synergy_id synid
				  FROM ".$this->_table." n
				  LEFT JOIN user u ON u.id = n.owner
				  WHERE code = '$categoryID'  AND deleted = 0 ";
		if ($archive)
			$query .= " AND (datetime_published <= CURDATE() OR owner = $userID) AND datetime_expires <  CURDATE()";
    	else
			$query .= " AND (datetime_published <= CURDATE() OR owner = $userID) AND datetime_expires >= CURDATE()";

		$query .= "ORDER BY datetime_posted DESC";

		if($config['size'] > 0)
			$query .= " LIMIT 0, " . $config['size'];

		return $this->db->getAll($query);
	}

	function homepageClean($cid) {
		$this->db->delete('subject_news', 'code', $cid);
	}

	function create($news) {
		$news['datetime_posted'] = 'NOW()';
		parent::create($news);
	}

	function delete($id) {
		$this->update(array('id'=>$id,'deleted'=>1));
	}

    function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
        $keyword = mysql_real_escape_string($keyword);

        $query = "SELECT `subject_news`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                        MATCH(`heading`,`body`) AGAINST ('$keyword') AS `score`
                    FROM `subject_news`
                    LEFT JOIN `user` ON `subject_news`.owner = `user`.id
                    LEFT JOIN `perms` ON `subject_news`.code = `perms`.cid
                        AND `perms`.gid = {$_SESSION['gid']}
                        AND `perms`.type > 0
                    LEFT JOIN `userAccess` ON `subject_news`.code = `userAccess`.sid
                        AND `userAccess`.service = 1
                        AND `userAccess`.uid = {$_SESSION['id']}
                    WHERE (`perms`.cid OR `userAccess`.sid)
                        AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                        AND (`subject_news`.datetime_published <= CURDATE() OR `subject_news`.owner = {$_SESSION['id']})
                        AND `deleted` = FALSE
                    ORDER BY `$sort_by` $sort_dir
                    LIMIT $offset, $limit;
        ";

        $count_query = "SELECT COUNT(*)
                        FROM `subject_news`
                        LEFT JOIN `user` ON `subject_news`.owner = `user`.id
                        LEFT JOIN `perms` ON `subject_news`.code = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `subject_news`.code = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                            AND (`subject_news`.datetime_published <= CURDATE() OR `subject_news`.owner = {$_SESSION['id']})
                            AND `deleted` = FALSE
        ";

        $count = $this->db->getOne($count_query);

        return $this->db->getAll($query);
    }
}

?>