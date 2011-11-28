<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2009 Lektec Consulting                *
*                                                           *
************************************************************/

class Feed {

	function Feed() {
		$this->db = new DeprecatedDatabase(SQL_db);
	}

	function getFeed($cid, $limit) {

		if($limit == 0 || $limit > 50)
			$limit = 50;


		$files = $this->getFiles($cid, $limit);
		$links = $this->getLinks($cid, $limit);
		$folders = $this->getFolders($cid, $limit);
		$news = $this->getNews($cid, $limit);
		$wiki = $this->getWiki($cid, $limit);
		$forum = $this->getForum($cid, $limit);
        $blog = $this->getBlog($cid, $limit);
        $blogComments = $this->getBlogComments($cid, $limit);
        $onlineAssessments = $this->getOnlineAssessments($cid, $limit);
		$surveys = $this->getSurveys($cid, $limit);

		is_array($files)?0:$files=array();
		is_array($links)?0:$links=array();
		is_array($folders)?0:$folders=array();
		is_array($news)?0:$news=array();
		is_array($wiki)?0:$wiki=array();
		is_array($forum)?0:$forum=array();
        is_array($blog)?0:$blog=array();
        is_array($blogComments)?0:$blogComments=array();
        is_array($onlineAssessments)?0:$onlineAssessments=array();
		is_array($surveys)?0:$surveys=array();

		//get each of the areas
		$feedList = array_merge($files, $links, $folders, $news, $wiki, $forum, $blog, $blogComments, $onlineAssessments, $surveys);


		//sort them
		usort($feedList,array($this,"timecmp"));

		//cut off over limit
		$feedList = array_slice($feedList, 0, $limit);

		return $feedList;
	}

	function getFiles($cid, $limit) {
		$query = "SELECT f.id, u.firstname, u.lastname, u.title, u.id as uid, UNIX_TIMESTAMP(f.submitted) submitted, f.description as name, realname,
						af.description as type, inline
				  FROM file f
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.category = $cid AND f.hidden = 0 AND f.deleted = 0
				  ORDER BY f.submitted DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){
			if ($row['realname'] != '') {
				if ($row['inline'] == 'true' && FILE_use_frame) {
					$url = "<a href=\"/sendframe.php?id=".$row['id']."\" target=\"_blank\">".Scrub::text($row['name'])."</a>";
				}
				else {
					$url = '<a href="/send.php?id='.$row['id'].'">'.Scrub::text($row['name']).'</a>';
				}
			}
			else {
				$url = Scrub::text($row['name']);
			}

			$file[] = array(
				'date' => $row['submitted'],
				'type' => 'file',
				'message' => $userHandler->formatName($row) . ' uploaded the file '.Scrub::text($row['name']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">' . $userHandler->formatName($row) . '</a> uploaded the file '.$url,
			);
		}

		return $file;
	}

	function getLinks($cid, $limit) {
		$query = "SELECT l.id, u.firstname, u.lastname, u.title, u.id as uid, UNIX_TIMESTAMP(l.submitted) submitted, description as name, url
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE category = $cid AND l.hidden = 0
				  ORDER BY l.submitted DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){
			$link[] = array(
				'date' => $row['submitted'],
				'type' => 'link',
				'message' => $userHandler->formatName($row).' submitted the link '.Scrub::text($row['name']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">'.$userHandler->formatName($row).'</a> submitted the link <a href="'.$row['url'].'">'.Scrub::text($row['name']).'</a>',
			);
		}
		return $link;
	}

	/**
	 * Get a list of online assessments that were created.
	 *
	 * @param  $cid
	 * @param  $limit
	 * @return
	 */
    public function getOnlineAssessments($cid, $limit) {
        $query = "SELECT
                      assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.created_at) as created_at,
                      user.title, user.firstname, user.lastname,
                      user.id AS uid
                  FROM assess
                  LEFT JOIN user ON assess.user_id = user.id
                  WHERE assess.category_id = '$cid'
                  AND assess.deleted_at IS NULL
                  AND assess.availability = 'assess'
                  ORDER BY assess.created_at DESC LIMIT 0, $limit";

        $this->db->query($query);

		$userHandler = new DeprecatedUser();

        while ($row = $this->db->getNextArray()) {
            $onlineAssessments[] = array(
                'date' => $row['created_at'],
                'type' => 'onlineassessment',
                'message' => $userHandler->formatName($row) . ' posted the online assessment ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/'.$row['uid'].'">' . $userHandler->formatName($row) . '</a> posted the online assessment <a href="/assess/intro/' . $row['assess_id'] . '">' . Scrub::text($row['heading']) . '</a>',
            );
        }

        return $onlineAssessments;
    }

	/**
	 * Get a list of surveys that were created.
	 *
	 * @param  $cid
	 * @param  $limit
	 * @return
	 */
	public function getSurveys($cid, $limit) {
		$query = "SELECT
					  assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.created_at) as created_at,
					  user.title, user.firstname, user.lastname,
					  user.id AS uid
				  FROM assess
				  LEFT JOIN user ON assess.user_id = user.id
				  WHERE assess.category_id = '$cid'
				  AND assess.deleted_at IS NULL
				  AND assess.availability = 'survey'
				  ORDER BY assess.created_at DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while ($row = $this->db->getNextArray()) {
			$surveys[] = array(
				'date' => $row['created_at'],
				'type' => 'survey',
				'message' => $userHandler->formatName($row) . ' posted the survey ' . Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">' . $userHandler->formatName($row) . '</a> posted the survey <a href="/survey/intro/' . $row['assess_id'] . '">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $surveys;
	}


	function getNews($cid, $limit) {
		$query = "SELECT sn.id, heading, UNIX_TIMESTAMP(datetime_posted) submitted, u.firstname, u.lastname, u.title, u.id as uid
					FROM subject_news sn JOIN user u ON owner = u.id WHERE code = $cid AND deleted = 0
					ORDER BY datetime_posted DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){

			$news[] = array(
				'date' => $row['submitted'],
				'type' => 'news',
				'message' => $userHandler->formatName($row) . ' posted the news '.Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">'.$userHandler->formatName($row) . '</a> posted the news <a href="/resources/'.$row['id'].'">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $news;
	}

	function getFolders($cid, $limit) {
		$query = "SELECT c.id, name, UNIX_TIMESTAMP(time) submitted, action, u.firstname, u.lastname, u.title, u.id as uid
					FROM cmsLog JOIN category c ON objectId = c.id JOIN user u ON user = u.id WHERE parent = $cid AND object = 'folder' AND action = 'create'
					ORDER BY time DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){
			$folder[] = array(
				'date' => $row['submitted'],
				'type' => 'folder',
				'message' => $userHandler->formatName($row) . ' created the folder '.Scrub::text($row['name']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">'.$userHandler->formatName($row) . '</a> created the folder <a href="/resources/'.$row['id'].'">' . Scrub::text($row['name']) . '</a>',
			);
		}

		return $folder;
	}

	function getForum($cid, $limit) {
		$query = "SELECT f.id, heading, UNIX_TIMESTAMP(`date`) submitted, u.firstname, u.lastname, u.title, u.id as uid
					FROM forum f
						JOIN user u ON u.id = f.owner
					WHERE f.deleted = 0 AND f.cid = $cid
					ORDER BY `date` DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){
			$forum[] = array(
				'date' => $row['submitted'],
				'type' => 'forum',
				'message' => $userHandler->formatName($row) . ' made the comment '.Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">'.$userHandler->formatName($row) . '</a> made the comment <a href="/forum/viewForum.php?mgsid='.$row['id'].'&cid='.$cid.'">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $forum;
	}

	function getPoll($limit) {

	}

	function getWiki($cid, $limit) {
		$query = "SELECT w.wikiID, heading, w.categoryID, w.slug, UNIX_TIMESTAMP(r.created) submitted, u.firstname, u.lastname, u.title, u.id as uid
					FROM wikiRevision r
						JOIN wiki w ON w.wikiID = r.wikiID
						JOIN user u ON u.id = r.userID
				  	WHERE w.deleted = 'false' AND w.categoryID = $cid
					ORDER BY r.created DESC LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while($row = $this->db->getNextArray()){
			$wiki[] = array(
				'date' => $row['submitted'],
				'type' => 'wiki',
				'message' => $userHandler->formatName($row) . ' updated the wiki '.Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/'.$row['uid'].'">'.$userHandler->formatName($row) . '</a> updated the wiki <a href="/wiki/'.$row['categoryID'].'/'.$row['slug'] .'">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $wiki;
	}

	/**
	 * Get a list of new blog entries to push into the feed.
	 *
	 * @param  $cid
	 * @param  $limit
	 * @return
	 */
	public function getBlog($cid, $limit) {
		$query = "SELECT u.id AS uid, u.title, u.firstname, u.lastname, b.id AS blogID, heading, UNIX_TIMESTAMP(b.created) AS submitted
			FROM user u
			JOIN blog b
			ON u.id = b.owner
			WHERE cid = $cid
			AND deleted IS NULL
			ORDER BY submitted LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while ($row = $this->db->getNextArray()) {
			$blog[] = array(
				'date' => $row['submitted'],
				'type' => 'blog',
				'message' => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName($row) . '</a> updated the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $blog;
	}

	/**
	 * Get a list of new blog comments to push into the feed.
	 *
	 * @param  $cid
	 * @param  $limit
	 * @return
	 */
	public function getBlogComments($cid, $limit) {
		$query = "SELECT u.id AS uid, u.title, u.firstname, u.lastname, UNIX_TIMESTAMP(bc.created) AS submitted, b.id AS blogID, heading, cid
			FROM `user` u
			JOIN blogComment bc ON u.id = bc.owner
			JOIN blog b ON bc.bid = b.id
			WHERE cid = $cid
			AND b.deleted IS NULL
			ORDER BY submitted LIMIT 0, $limit";

		$this->db->query($query);

		$userHandler = new DeprecatedUser();

		while ($row = $this->db->getNextArray()) {
			$blogComments[] = array(
				'date' => $row['submitted'],
				'type' => 'blog',
				'message' => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
				'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName($row) . '</a> commented on the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text($row['heading']) . '</a>',
			);
		}

		return $blogComments;
	}

	// TODO: Group member changes

	// TODO: Homepage layout changes

	// TODO: Submission boxes

	// TODO: blog

	// TODO: text box updates

	function timecmp($a, $b)
	{
		//just a quick function to enable sorting of news based on time
	   if ($a['date'] == $b['date']) {
		   return 0;
	   }
	   return ($a['date'] > $b['date']) ? -1 : 1;
	}


}

?>
