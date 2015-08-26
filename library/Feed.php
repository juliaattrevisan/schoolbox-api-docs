<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/

class Feed {

	/**
	 * @deprecated use \Schoolbox\Feed
	 */
	function Feed() {
	    $this->db = DeprecatedDatabase::getInstance();
    }

	/**
	 * @deprecated use \Schoolbox\Feed::aggregate()
	 * @param $cid
	 * @param $limit
	 * @return array
	 */
	function getFeed($cid, $limit) {

        if ($limit == 0 || $limit > 50) {
            $limit = 50;
        }

		/* ALL THESE HELPER FUNCTIONS TO BE SYSTEMATICALLY DEPRECATED */

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

        is_array($files) ? 0 : $files = array();
        is_array($links) ? 0 : $links = array();
        is_array($folders) ? 0 : $folders = array();
        is_array($news) ? 0 : $news = array();
        is_array($wiki) ? 0 : $wiki = array();
        is_array($forum) ? 0 : $forum = array();
        is_array($blog) ? 0 : $blog = array();
        is_array($blogComments) ? 0 : $blogComments = array();
        is_array($onlineAssessments) ? 0 : $onlineAssessments = array();
        is_array($surveys) ? 0 : $surveys = array();

        //get each of the areas
        $feedList = array_merge(
            $files, $links, $folders, $news, $wiki, $forum, $blog, $blogComments, $onlineAssessments, $surveys
        );


        //sort them
        usort($feedList, array($this, "timecmp"));

        //cut off over limit
        $feedList = array_slice($feedList, 0, $limit);

        return $feedList;
    }

    /**
     * Generic filter function for get* methods.
     *
     * @param  mixed  $cid Either a number string, or an array of IDs
     * @return string
     */
    protected function getFilterFromCategoryId($cid) {
        if (is_array($cid) && !empty($cid)) {
            return ' IN (' . implode(',', array_map('intval', $cid)) . ') ';
        }
        else {
            return " = ".intval($cid);
        }
    }

    function getFiles($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT f.id, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, UNIX_TIMESTAMP(f.submitted) submitted, f.description as name, realname,
						af.description as type, inline, c.name as cat_name, c.id as cat_id
				  FROM file f
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  LEFT JOIN category c ON c.id = f.category
				  WHERE f.category $where AND f.hidden = 0 AND f.deleted = 0
				  ORDER BY f.submitted DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $link = null;
            if ($row['realname'] != '') {
                $url = '<a href="/send.php?id=' . $row['id'] . '" rel="external">' . Scrub::text($row['name']) . '</a>';
                $link = '/send.php?id=' . $row['id'];
            } else {
                $url = Scrub::text($row['name']);
            }

            $file[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-files',
                'message' => $userHandler->formatName($row) . ' uploaded the file ' . Scrub::text($row['name']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> uploaded the file ' . $url,
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],

                'user_id' => $row['uid'],
                'link'    => $link,
                'name'    => $row['name'],
                'action'  => 'uploaded the file',
            );
        }

        return $file;
    }

    function getLinks($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT l.id, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, UNIX_TIMESTAMP(l.submitted) submitted, description as name, url, c.name as cat_name, c.id as cat_id
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  LEFT JOIN category c ON c.id = l.category
				  WHERE category $where AND l.hidden = 0
				  ORDER BY l.submitted DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {

            $url = new Url($row['url']);
            if ($url->isLocalUrl()) {
                $linkUrl = $url->getRelativeURL();
                $linkTarget = '';
            } else {
                $linkUrl = $url->getBase64EncodedRedirect();
                $linkTarget = 'external';
            }

            $link[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-link',
                'message' => $userHandler->formatName($row) . ' submitted the link ' . Scrub::text($row['name']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> submitted the link <a href="' . $linkUrl . '" rel="' . $linkTarget . '">' . Scrub::text(
                    $row['name']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],

                'user_id' => $row['uid'],
                'link'    => $linkUrl,
                'name'    => $row['name'],
                'action'  => 'submitted the link',
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

        $where = $this->getFilterFromCategoryId($cid);

        $query = "
          SELECT
              assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.start) as start,
              user.title, user.firstname, user.lastname,
              user.id AS uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
          FROM assess
          LEFT JOIN user ON assess.user_id = user.id
          LEFT JOIN category c ON c.id = assess.category_id
          WHERE assess.category_id $where
          AND assess.start < NOW()
          AND assess.deleted_at IS NULL
          AND assess.availability = 'assess'
          ORDER BY assess.created_at DESC LIMIT 0, $limit
        ";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $onlineAssessments[] = array(
                'date' => $row['start'],
                'type' => 'icon-quiz',
                'message' => $userHandler->formatName($row)
                    . ' opened the quiz '
                    . Scrub::text( $row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName($row)
                    . '</a> opened the quiz <a href="/assess/intro/' . $row['assess_id'] . '">'
                    . Scrub::text($row['heading']) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/assess/intro/' . $row['assess_id'],
                'name'    => $row['heading'],
                'action'  => 'opened the quiz',
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

        $where = $this->getFilterFromCategoryId($cid);

        $query = "
          SELECT
              assess.assess_id, assess.title AS heading, UNIX_TIMESTAMP(assess.start) as start,
              user.title, user.firstname, user.lastname,
              user.id AS uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
          FROM assess
          LEFT JOIN user ON assess.user_id = user.id
          LEFT JOIN category c ON c.id = assess.category_id
          WHERE assess.category_id $where
          AND assess.start < NOW()
          AND assess.deleted_at IS NULL
          AND assess.availability = 'survey'
          ORDER BY assess.created_at DESC LIMIT 0, $limit
        ";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $surveys[] = array(
                'date' => $row['start'],
                'type' => 'icon-surveys',
                'message' => $userHandler->formatName($row)
                    . ' opened the survey '
                    . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName($row)
                    . '</a> opened the survey <a href="/survey/intro/' . $row['assess_id'] . '">'
                    . Scrub::text($row['heading']) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/survey/intro/' . $row['assess_id'],
                'name'    => $row['heading'],
                'action'  => 'opened the survey',
            );
        }

        return $surveys;
    }


	/**
	 * @deprecated use \Schoolbox\Resource\Category\NewsNotifier::getFeed()
	 * @param $cid
	 * @param $limit
	 * @return array
	 */
	function getNews($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT sn.id, heading, UNIX_TIMESTAMP(datetime_posted) submitted, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
					FROM subject_news sn
					JOIN user u ON owner = u.id
					LEFT JOIN category c ON c.id = sn.code
					WHERE sn.code $where AND deleted = 0
					ORDER BY datetime_posted DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {

            $news[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-news',
                'message' => $userHandler->formatName($row) . ' posted the news ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName( $row ) .
                                 '</a> posted the news '. Scrub::text( $row['heading'] ),
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => null,
                'name'    => $row['heading'],
                'action'  => 'posted the news',
            );
        }

        return $news;
    }

    function getFolders($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT c.id, name, UNIX_TIMESTAMP(time) submitted, action, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
					FROM cmsLog
					JOIN category c ON objectId = c.id
					JOIN user u ON user = u.id
					WHERE parent $where AND object = 'folder' AND action = 'create'
					ORDER BY time DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $folder[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-folder',
                'message' => $userHandler->formatName($row) . ' created the folder ' . Scrub::text($row['name']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> created the folder <a href="/homepage/' . $row['id'] . '">' . Scrub::text(
                    $row['name']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/homepage/' . $row['id'],
                'name'    => $row['name'],
                'action'  => 'created the folder',
            );
        }

        return $folder;
    }

    function getForum($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT f.threadID, f.title as heading,  UNIX_TIMESTAMP(`date`) submitted, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
					FROM forumThread f
                    JOIN user u ON u.id = f.userID
                    LEFT JOIN category c ON c.id = f.categoryID
					WHERE f.deleted = 'false' AND f.categoryID $where
					ORDER BY `date` DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {

            $forum[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-forum',
                'message' => $userHandler->formatName($row) . ' started the thread ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> started the thread <a href="/forum/thread/' . $row['threadID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/forum/thread/' . $row['threadID'],
                'name'    => $row['heading'],
                'action'  => 'started the thread',
            );
        }

        return $forum;
    }

    function getPoll($limit) {

    }

	/**
	 * @deprecated use \Schoolbox\Wiki\WikiNotifier::getFeed()
	 * @param $cid
	 * @param $limit
	 * @return array
	 */
	function getWiki($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT w.wikiID, heading, w.categoryID, w.slug, UNIX_TIMESTAMP(r.created) submitted, u.firstname, u.lastname, u.title, u.id as uid, synergy_id as external_id, c.name as cat_name, c.id as cat_id
					FROM wikiRevision r
						JOIN wiki w ON w.wikiID = r.wikiID
						JOIN user u ON u.id = r.userID
						LEFT JOIN category c ON c.id = w.categoryID
				  	WHERE w.deleted = 'false' AND w.categoryID $where
					ORDER BY r.created DESC LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $wiki[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-wiki',
                'message' => $userHandler->formatName($row) . ' updated the wiki ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> updated the wiki <a href="/wiki/' . $row['categoryID'] . '/' . $row['slug'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/wiki/' . $row['categoryID'] . '/' . $row['slug'],
                'name'    => $row['heading'],
                'action'  => 'updated the wiki',
            );
        }

        return $wiki;
    }

    /**
     * Get a list of new blog entries to push into the feed.
     *
     * @deprecated use \Schoolbox\Blog\BlogNotifier::getFeed()
     * @param  $cid
     * @param  $limit
     * @return
     */
    public function getBlog($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT u.id AS uid, u.title, u.firstname, u.lastname, synergy_id as external_id, b.id AS blogID, heading, UNIX_TIMESTAMP(b.created) AS submitted, c.name as cat_name, c.id as cat_id
			FROM user u
			JOIN blog b
			ON u.id = b.owner
			LEFT JOIN category c ON c.id = cid
			WHERE cid $where
			AND deleted IS NULL
			ORDER BY submitted LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $blog[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-blog',
                'message' => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> updated the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/blog/' . $row['blogID'],
                'name'    => $row['heading'],
                'action'  => 'updated the blog',
            );
        }

        return $blog;
    }

    /**
     * Get a list of new blog comments to push into the feed.
     *
     * @deprecated use \Schoolbox\Blog\CommentNotifier::getFeed()
     * @param  $cid
     * @param  $limit
     * @return
     */
    public function getBlogComments($cid, $limit) {

        $where = $this->getFilterFromCategoryId($cid);

        $query = "SELECT u.id AS uid, u.title, u.firstname, u.lastname, synergy_id as external_id, UNIX_TIMESTAMP(bc.created) AS submitted, b.id AS blogID, heading, cid, c.name as cat_name, c.id as cat_id
			FROM `user` u
			JOIN blogComment bc ON u.id = bc.owner
			JOIN blog b ON bc.bid = b.id
			LEFT JOIN category c ON c.id = cid
			WHERE cid $where
			AND b.deleted IS NULL
			ORDER BY submitted LIMIT 0, $limit";

        $this->db->query($query);

        $userHandler = new User();

        while ($row = $this->db->getNextArray()) {
            $blogComments[] = array(
                'date' => $row['submitted'],
                'type' => 'icon-blog',
                'message' => $userHandler->formatName($row) . ' updated the blog ' . Scrub::text($row['heading']),
                'messageHTML' => '<a href="/search/user/' . $row['uid'] . '">' . $userHandler->formatName(
                    $row
                ) . '</a> commented on the blog <a href="/blog/' . $row['blogID'] . '">' . Scrub::text(
                    $row['heading']
                ) . '</a>',
                'external_id' => $row['external_id'],
                'user_name' => $userHandler->formatName($row),
                'cat_name' => $row['cat_name'],
                'cat_id' => $row['cat_id'],
                'user_id' => $row['uid'],
                'link'    => '/blog/' . $row['blogID'],
                'name'    => $row['heading'],
                'action'  => 'commented on the blog',
            );
        }

        return $blogComments;
    }

    // TODO: Group member changes

    // TODO: Homepage layout changes

    // TODO: Submission boxes

    // TODO: blog

    // TODO: text box updates

    function timecmp($a, $b) {
        //just a quick function to enable sorting of news based on time
        if ($a['date'] == $b['date']) {
            return 0;
        }
        return ($a['date'] > $b['date']) ? -1 : 1;
    }


}

?>
