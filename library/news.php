<?php

class News extends DeprecatedModel {

    function News() {

        $this->_table = "news_items";
        $this->_index = "item_id";

        parent::DeprecatedModel();
    }

    function getByID($newsID) {

        $query = "SELECT * FROM " . $this->_table . " WHERE " . $this->_index . " = $newsID AND item_deleted = 0";
        $news = $this->db->getRow($query);

        if ($news[$this->_index]) {
            $query = "SELECT * FROM newsGroup WHERE id = " . $news[$this->_index];
            $groupList = $this->db->getAll($query);
            if (is_array($groupList)) {
                foreach ($groupList as $group) {
                    $news['groupList'][] = $group['gid'];
                }
            }

            $query = "SELECT * FROM newsCampus WHERE id = " . $news[$this->_index];
            $campusList = $this->db->getAll($query);
            if (is_array($campusList)) {
                foreach ($campusList as $campus) {
                    $news['campusList'][] = $campus['campus'];
                }
            }

            $query = "SELECT id, username, title, firstname, lastname
                                    FROM newsUser JOIN user ON user.id = newsUser.userID
                                    WHERE newsID = {$news[$this->_index]}";
            $news['userList'] = $this->db->getAll($query);

        }
        return $news;
    }

    function getGroupList() {
        $query = "SELECT id, name FROM groups";
        $groupList = $this->db->getAll($query);
        if (is_array($groupList)) {
            $i = 0;
            foreach ($groupList as $group) {
                $output[$i]['GROUP_ID'] = $group['id'];
                $output[$i++]['GROUP_NAME'] = $group['name'];
            }
        }
        return $output;
    }

    function getCampusList() {
        $query = "SELECT id, name FROM luCampus";
        $campusList = $this->db->getAll($query);
        if (is_array($campusList)) {
            $i = 0;
            foreach ($campusList as $campus) {
                $output[$i]['CAMPUS_ID'] = $campus['id'];
                $output[$i++]['CAMPUS_NAME'] = $campus['name'];
            }
        }
        return $output;
    }

    function getUserList($newsID) {
        $query = "SELECT id, title, firstname, lastname FROM newsUser JOIN user ON userID = id WHERE newsID = $newsID";
        return $this->db->getAll($query);
    }

    function hasWrite($newsID) {
        $groupHandler = new Group(NEWS_SERVICE);

        $permLevel = $groupHandler->getPermission(0);
        $news = $this->getById($newsID);
        if (($permLevel >= 2 && $news['item_owner'] == $_SESSION['id']) || $permLevel >= 3) {
            return true;
        }
        return false;
    }

    function create($news) {
        $groupList = $news['groupList'];
        $campusList = $news['campusList'];
        $userList = $news['userList'];

        unset($news['groupList']);
        unset($news['campusList']);
        unset($news['userList']);


        $news['item_datetime_posted'] = 'NOW()';
        $news['item_datetime_modified'] = 'NOW()';
        $news['item_owner'] = $_SESSION['id'];

        $newsID = parent::create($news);

        if (is_array($groupList)) {
            foreach ($groupList as $group) {
                $this->db->insert('newsGroup', array('id' => $newsID, 'gid' => $group));
            }
        } else {
            if (is_numeric($groupList)) {
                $this->db->insert('newsGroup', array('id' => $newsID, 'gid' => $groupList));
            }
        }

        if (is_array($campusList)) {
            foreach ($campusList as $campus) {
                $this->db->insert('newsCampus', array('id' => $newsID, 'campus' => $campus));
            }
        } else {
            if (is_numeric($campusList)) {
                $this->db->insert('newsCampus', array('id' => $newsID, 'campus' => $campusList));
            } else {
                $this->db->insert('newsCampus', array('id' => $newsID, 'campus' => 1));
            }
        }

        if (is_array($userList)) {
            foreach ($userList as $user) {
                $this->db->insert('newsUser', array('newsID' => $newsID, 'userID' => $user));
            }
        }

        return $newsID;
    }

    function update($news) {

        if (!$this->hasWrite($news['item_id'])) {
            return;
        }

        $groupList = $news['groupList'];
        $campusList = $news['campusList'];
        $userList = $news['userList'];

        $news['item_datetime_modified'] = 'NOW()';

        unset($news['groupList']);
        unset($news['campusList']);
        unset($news['userList']);

        parent::update($news);

        if (is_array($groupList)) {
            $this->db->delete('newsGroup', 'id', $news['item_id']);
            foreach ($groupList as $group) {
                $this->db->insert('newsGroup', array('id' => $news['item_id'], 'gid' => $group));
            }
        }
        if (is_array($campusList)) {
            $this->db->delete('newsCampus', 'id', $news['item_id']);
            foreach ($campusList as $campus) {
                $this->db->insert('newsCampus', array('id' => $news['item_id'], 'campus' => $campus));
            }
        }
        if (is_array($userList)) {
            $this->db->delete('newsUser', 'newsID', $news['item_id']);
            foreach ($userList as $user) {
                $this->db->insert('newsUser', array('newsID' => $news['item_id'], 'userID' => $user));
            }
        }

    }

    function delete($newsID) {
        $newsID = (int)$newsID;

        if (!$this->hasWrite($newsID)) {
            return;
        }

        $query = "UPDATE " . $this->_table . " SET item_deleted = 1 WHERE " . $this->_index . " = $newsID";
        return $this->db->query($query);
    }

    function getHeadlines() {
        $userHandler = new Group;
        $shareResults = array();

        /* Prevent copies of posts appearing if they are posted to another group.
        */
        if (!$userHandler->isSuperuser()) {
            $query = "
         SELECT `" . $this->_index . "`, item_heading, item_datetime_posted, item_datetime_modified, newsTopic.name topicName, newsTopicID, item_image
         FROM `" . $this->_table . "`
            JOIN newsCampus ON newsCampus.id = news_items.item_id
               AND newsCampus.campus IN(" . implode(',', $_SESSION['campus']) . ")
            JOIN newsGroup ON newsGroup.id = news_items.item_id
               AND newsGroup.gid = '" . $_SESSION['gid'] . "'
            JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
         WHERE news_items.item_owner != '" . $_SESSION['id'] . "'
            AND item_datetime_published <= CURDATE()
            AND item_datetime_expires   >= CURDATE()
            AND item_deleted = 0
         GROUP BY `" . $this->_index . "`
         ORDER BY newsTopic.newsTopicID, item_datetime_posted DESC
         ";

            $shareResults = $this->db->getAll($query);
        }

        $query = "
		SELECT DISTINCT `" . $this->_index . "`, item_heading, item_datetime_posted, item_datetime_modified, newsTopic.name topicName, newsTopicID
		FROM `" . $this->_table . "`
                LEFT JOIN newsUser ON newsUser.newsID = news_items.item_id
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id";

        /* If the current user is a superuser, do not filter their results.
        * Otherwise, restrict news post selection by ownership.
        */
        if (!$userHandler->isSuperuser()) {
            $query .= " WHERE (item_owner = '" . $_SESSION['id'] . "' OR (newsUser.userID = '" . $_SESSION['id'] . "' AND item_datetime_published <= CURDATE())) ";
        }

        $query .= "
			AND item_datetime_expires >= CURDATE()
			AND item_deleted = 0
		GROUP BY `" . $this->_index . "`
		ORDER BY newsTopic.newsTopicID, item_datetime_posted DESC
		";

        $userResults = $this->db->getAll($query);

        //merge and sort the headlines based on time
        if (is_array($shareResults) && is_array($userResults)) {
            $headlines = array_merge($shareResults, $userResults);
        } else {
            if (is_array($shareResults)) {
                $headlines = $shareResults;
            } else {
                if (is_array($userResults)) {
                    $headlines = $userResults;
                } else {
                    return false;
                }
            }
        }

        usort($headlines, array($this, "cattimecmp"));

        return $headlines;
    }

    function getNewsListing($archive = true, $topic = '', $year = 0, $month = 0, $index = 0, $limit = 0, $orderByDate = false) {
        $userHandler = new Group;
        $shareResults = array();

        /* Prevent copies of posts appearing if they are posted to another group.
        */

        if ($topic == '' || $topic == 'all') {
            $topicFilter = '';
        } else {
            $topicFilter = " AND newsTopic.name = '$topic' ";
        }

        $yearFilter = $year != 0 ? " AND YEAR(news_items.item_datetime_published) = $year " : '';
        $monthFilter = $month != 0 ? " AND MONTH(news_items.item_datetime_published) = $month " : '';
        $beforeOrAfter = $archive ? '<' : '>=';

        if (!$userHandler->isSuperuser()) {
            $query = "
         SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner, news_items.item_body,
            UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, item_image,
            news_items.item_datetime_posted, news_items.item_datetime_modified, news_items.item_datetime_published,
            UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime,
            user.lastname, user.title, user.id as uid, user.firstname, user.synergy_id synid, newsTopic.name topicName, newsTopicID
         FROM `" . $this->_table . "`
         LEFT JOIN user ON news_items.item_owner = user.id
         JOIN newsCampus ON newsCampus.id = news_items.item_id
            AND newsCampus.campus IN(" . implode(',', $_SESSION['campus']) . ")
         JOIN newsGroup ON newsGroup.id = news_items.item_id
            AND newsGroup.gid = '" . $_SESSION['gid'] . "'
         JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
         WHERE news_items.item_owner != '" . $_SESSION['id'] . "'
            AND news_items.item_deleted = 0
            AND item_datetime_published <= CURDATE()
            AND news_items.item_datetime_expires $beforeOrAfter CURDATE()
            $topicFilter $yearFilter $monthFilter
         GROUP BY news_items.item_id
         ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC";

            $shareResults = $this->db->getAll($query);
        }

        $query = "
		SELECT DISTINCT news_items.item_id, news_items.item_heading, news_items.item_owner, news_items.item_body, item_image,
			UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
			UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime, news_items.item_datetime_published,
			user.lastname, user.title, user.firstname, user.id as uid, user.synergy_id synid, newsTopic.name topicName, newsTopicID
		FROM `" . $this->_table . "`
		LEFT JOIN user ON news_items.item_owner = user.id
        LEFT JOIN newsUser ON newsUser.newsID = news_items.item_id
        LEFT JOIN newsCampus ON newsCampus.id = news_items.item_id
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
        WHERE news_items.item_deleted = 0";

        /* If the current user is a superuser, do not filter their results.
         * Otherwise, restrict news post selection by ownership
         * (or group moderation)
         */
        if (!$userHandler->isSuperuser()) {
            $parts = array(
                "news_items.item_owner = '" . $_SESSION['id'] ."'",
                "(newsUser.userID = '" . $_SESSION['id'] . "' AND item_datetime_published <= CURDATE())",
            );
            //
            // Check if this user can moderate any groups, add them to this list
            $campus_list = array();
            foreach ($userHandler->getAllUserAccess($_SESSION['id']) as $access) {
                if ($access['service'] == \Schoolbox\UserAccess::SERVICE_NEWS
                    && $access['type'] >= \Schoolbox\UserAccess::ACCESS_FULL)
                {
                    $campus_list[] = $access['sid'];
                }
            }
            if (!empty($campus_list)) {
                $parts[] = 'newsCampus.campus IN ('.implode(',', $campus_list).')';
            }
            $query .= ' AND ('.implode(' OR ', $parts).')';
        }

        $query .= " AND news_items.item_datetime_expires $beforeOrAfter CURDATE()
                    $topicFilter $yearFilter $monthFilter
                    ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC;";

        $userResults = $this->db->getAll($query);


        // iterate over news articles and include them if their key is not already found in the index
        $existingIDs = array();
        $allNews = array();

        if (is_array($shareResults)) {
            foreach ($shareResults as $share) {
                if (!in_array($share['item_id'], $existingIDs)) {
                    $existingIDs[] = $share['item_id'];
                    $allNews[] = $share;
                }
            }
        }

        if (is_array($userResults)) {
            foreach ($userResults as $user) {
                if (!in_array($user['item_id'], $existingIDs)) {
                    $existingIDs[] = $user['item_id'];
                    $allNews[] = $user;
                }
            }
        }

        if (empty($allNews)) {
            return false;
        }

        // depending on the ordering required, sort the news
        if($orderByDate) {
            usort($allNews, array($this, "timecmp"));
        }
        else {
            usort($allNews, array($this, "cattimecmp"));
        }

        if ($limit > 0) {
            $allNews = array_slice($allNews, $index, $limit);
        }

        return $allNews;
    }


    public function getFilteredNews($userID = 0, $campus = array(), $groupID = 0, $id = 0, $archive = false, $index = 0, $limit = 0) {
        $userHandler = new Group;
        $shareResults = array();

        /* Prevent copies of posts appearing if they are posted to another group.
        */
        if (!$userHandler->isSuperuser($userID)) {
            //
            // No campuses: exit out in order to avoid query error
            $campus_list = trim(implode(',', $campus));
            if (strlen($campus_list) === 0) {
                return array();
            }
            $query = "
         SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner, news_items.item_body, item_image,
            UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
            UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime,
            user.lastname, user.title, user.id as uid, user.firstname, newsTopic.name topicName, newsTopicID
         FROM `" . $this->_table . "`
         LEFT JOIN user ON news_items.item_owner = user.id
         JOIN newsCampus ON newsCampus.id = news_items.item_id
            AND newsCampus.campus IN(" . $campus_list . ")
         JOIN newsGroup ON newsGroup.id = news_items.item_id
            AND newsGroup.gid = '" . $groupID . "'
         JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
         WHERE news_items.item_owner != '" . $userID . "' AND news_items.item_deleted = 0";

            if ($id) {
                $query .= " AND news_items.item_id = '$id'";
            } else {
                if ($archive) {
                    $query .= " AND item_datetime_published <= CURDATE() AND news_items.item_datetime_expires <  CURDATE() ";
                } else {
                    $query .= " AND item_datetime_published <= CURDATE() AND news_items.item_datetime_expires >= CURDATE() ";
                }
            }


            $query .= " GROUP BY news_items.item_id ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC";

            $shareResults = $this->db->getAll($query);
        }

        $query = "
		SELECT DISTINCT news_items.item_id, news_items.item_heading, news_items.item_owner, news_items.item_body, item_image,
			UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
			UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime,
			user.lastname, user.title, user.firstname, user.id as uid, newsTopic.name topicName, newsTopicID
		FROM `" . $this->_table . "`
		LEFT JOIN user ON news_items.item_owner = user.id
                LEFT JOIN newsUser ON newsUser.newsID = news_items.item_id
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
      WHERE news_items.item_deleted = 0";

        /* If the current user is a superuser, do not filter their results.
        * Otherwise, restrict news post selection by ownership.
        */
        if (!$userHandler->isSuperuser($userID)) {
            $query .= " AND (news_items.item_owner = '" . $userID . "' OR (newsUser.userID = '" . $userID . "' AND item_datetime_published <= CURDATE() )) ";
        }

        if ($id) {
            $query .= " AND news_items.item_id = '$id'";
        } else {
            if ($archive) {
                $query .= " AND news_items.item_datetime_expires <  CURDATE() ";
            } else {
                $query .= " AND news_items.item_datetime_expires >= CURDATE() ";
            }
        }

        $query .= " ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC;";
        $userResults = $this->db->getAll($query);

        //merge and sort the headlines based on time
        if (is_array($shareResults) && is_array($userResults)) {
            $allNews = array_merge($shareResults, $userResults);
        } else {
            if (is_array($shareResults)) {
                $allNews = $shareResults;
            } else {
                if (is_array($userResults)) {
                    $allNews = $userResults;
                } else {
                    return false;
                }
            }
        }

        usort($allNews, array($this, "timecmp"));

        if ($limit > 0) {
            $allNews = array_slice($allNews, $index, $limit);
        }

        return $allNews;
    }

    /**
     * @param int $id
     * @param bool $archive
     * @param int $index
     * @param int $limit
     * @return array|bool
     */
    function getNews($id = 0, $archive = false, $index = 0, $limit = 0) {
        return $this->getFilteredNews(
            $_SESSION['id'], $_SESSION['campus'], $_SESSION['gid'], $id, $archive, $index, $limit
        );
    }

    function getDailyNotice($limit = 1, $campus = '') {
        $query = "SELECT item_heading, UNIX_TIMESTAMP(item_datetime_posted) item_datetime_posted, UNIX_TIMESTAMP(item_datetime_modified) item_datetime_modified, item_body
				  FROM news_items n ";

        if (is_array($campus)) {
            $query .= "JOIN newsCampus c ON c.id = n.item_id AND c.campus IN (" . implode(',', $campus) . ") ";
        }

        $query .= "WHERE item_heading LIKE '%Daily Notices%' ORDER BY item_datetime_posted DESC ";

        if ($limit) {
            $query .= "LIMIT $limit";
        }
        return $this->db->getAll($query);
    }

    function getAllByGroup($groupList, $campusList) {
        $query = "SELECT `" . $this->_index . "`, item_heading, UNIX_TIMESTAMP(item_datetime_posted) item_datetime_posted, UNIX_TIMESTAMP(item_datetime_modified) item_datetime_modified, item_body, newsTopic.name topicName
				  FROM `" . $this->_table . "`
				  JOIN user ON news_items.item_owner = user.id
				  JOIN newsCampus ON newsCampus.id = news_items.item_id
					AND newsCampus.campus IN(" . implode(',', $campusList) . ")
				  JOIN newsGroup ON newsGroup.id = news_items.item_id
					AND newsGroup.gid IN (" . implode(',', $groupList) . ")
				  JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
				  WHERE item_datetime_expires   >= CURDATE()
				    AND item_datetime_published <= CURDATE()
					AND item_deleted = 0
				  GROUP BY `" . $this->_index . "`
				  ORDER BY item_datetime_posted DESC";

        return $this->db->getAll($query);

    }

    /**
     * Comparison logic for posted-time only
     * @param $a
     * @param $b
     * @return int
     */
    function timecmp($a, $b) {

        //if ($a['newsTopicID'] == $b['newsTopicID']) {
        //just a quick function to enable sorting of news based on time
        if ($a['item_datetime_posted'] == $b['item_datetime_posted']) {
            return 0;
        }
        return ($a['item_datetime_posted'] > $b['item_datetime_posted']) ? -1 : 1;
        //} else {
        //	return $a['newsTopicID'] > $b['newsTopicID']?1:-1;
        //}
    }

    /**
     * Comparison logic for posted-time SUBORDINATE TO topic
     * @param $a
     * @param $b
     * @return int
     */
    function cattimecmp($a, $b) {

        if ($a['newsTopicID'] == $b['newsTopicID']) {
            //just a quick function to enable sorting of news based on time
            if ($a['item_datetime_posted'] == $b['item_datetime_posted']) {
                return 0;
            }
            return ($a['item_datetime_posted'] > $b['item_datetime_posted']) ? -1 : 1;
        } else {
            return $a['newsTopicID'] > $b['newsTopicID'] ? 1 : -1;
        }
    }

    function getTopicByID($newsTopicID) {
        $query = "SELECT * FROM `newsTopic` WHERE `newsTopicID` = $newsTopicID";
        return $this->db->getRow($query);
    }

    function getTopicList() {
        $query = "SELECT * FROM `newsTopic` ORDER BY `name`";
        return $this->db->getAll($query);
    }

    /**
     * Returns a list of years and months which have news articles
     *
     * @param Integer $categoryId
     * @return Array|Bool
     */
    public function getOccupiedDateList($archive = false, $topicName = '') {

        // provide news listing
        $visibleNews = $this->getNewsListing($archive, $topicName);

        // go through and extract the years and months
        $occupiedDates = array();
        if ($visibleNews) {
            foreach ($visibleNews as $article) {
                $df = new Alaress_Date_Format($article['item_datetime_published'], 'y-m-d h:i:s');
                $occupation = array(
                    'year' => $df->getUsingFormat('Y'),
                    'month' => $df->getUsingFormat('m')
                );
                if (!in_array($occupation, $occupiedDates)) {
                    $occupiedDates[] = $occupation;
                }
            }
        } else {
            return null;
        }

        // sort the generated list so the dates collate properly
        sort($occupiedDates);

        return $occupiedDates;

        /*
                $beforeOrAfter = $archive ? '<=' : '>';

                $query = "SELECT YEAR(`item_datetime_published`) as 'year', item_datetime_published,
                                 MONTH(`item_datetime_published`) as 'month'
                          FROM `news_items`";

                if($topicName != '' && $topicName != 'all')
                    $query .= " LEFT JOIN newsTopic
                                ON news_items.item_topic_id = newsTopic.newsTopicID
                                WHERE `name` = '$topicName'
                                AND ";
                else
                    $query .= " WHERE";

                $query .= " item_datetime_expires $beforeOrAfter NOW()
                            GROUP BY `year` DESC, `month` DESC";

                return $this->db->getAll($query);
        */
    }

    function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc') {
        $keyword = mysql_real_escape_string($keyword);
        /*
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
                                AND (`subject_news`.datetime_published < NOW() OR `subject_news`.owner = {$_SESSION['id']})
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
                                    AND (`subject_news`.datetime_published < NOW() OR `subject_news`.owner = {$_SESSION['id']})
                                    AND `deleted` = FALSE
                ";
        */

        $userHandler = new Group;
        $shareResults = array();

        /* Prevent copies of posts appearing if they are posted to another group.
        */
        if (!$userHandler->isSuperuser()) {
            $query = "
        SELECT DISTINCT(news_items.item_id), news_items.item_heading, news_items.item_owner, news_items.item_body,
            UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
            UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime,
            user.lastname, user.title, user.id as uid, user.firstname, newsTopic.name topicName, newsTopicID,
            MATCH(`item_heading`,`item_body`) AGAINST ('$keyword') AS `score`
        FROM `" . $this->_table . "`
        LEFT JOIN user ON news_items.item_owner = user.id
        JOIN newsCampus ON newsCampus.id = news_items.item_id
            AND newsCampus.campus IN(" . implode(',', $_SESSION['campus']) . ")
        JOIN newsGroup ON newsGroup.id = news_items.item_id
            AND newsGroup.gid = '" . $_SESSION['gid'] . "'
        JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
        WHERE news_items.item_owner != '" . $_SESSION['id'] . "' AND news_items.item_deleted = 0
            AND item_datetime_published <= CURDATE()
            AND MATCH(`item_heading`,`item_body`) AGAINST ('$keyword')
        GROUP BY news_items.item_id
        ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC";

            $shareResults = $this->db->getAll($query);
        }

        $query = "
		SELECT DISTINCT news_items.item_id, news_items.item_heading, news_items.item_owner, news_items.item_body,
			UNIX_TIMESTAMP(news_items.item_datetime_posted) AS post_datetime, news_items.item_datetime_posted, news_items.item_datetime_modified,
			UNIX_TIMESTAMP(news_items.item_datetime_expires) AS expire_datetime, news_items.item_datetime_expires,
			user.lastname, user.title, user.firstname, user.id as uid, newsTopic.name topicName, newsTopicID,
		    MATCH(`item_heading`,`item_body`) AGAINST ('$keyword') AS `score`
		FROM `" . $this->_table . "`
		LEFT JOIN user ON news_items.item_owner = user.id
        LEFT JOIN newsUser ON newsUser.newsID = news_items.item_id
		JOIN newsTopic ON newsTopic.newsTopicID = news_items.item_topic_id
        WHERE news_items.item_deleted = 0
        ";

        /* If the current user is a superuser, do not filter their results.
        * Otherwise, restrict news post selection by ownership.
        */
        if (!$userHandler->isSuperuser()) {
            $query .= " AND (news_items.item_owner = '" . $_SESSION['id'] . "' OR (newsUser.userID = '" . $_SESSION['id'] . "' AND item_datetime_published <= CURDATE() )) ";
        }

        $query .= " AND MATCH(`item_heading`,`item_body`) AGAINST ('$keyword')
                    ORDER BY newsTopic.newsTopicID, news_items.item_datetime_posted DESC;";

        $userResults = $this->db->getAll($query);

        //merge and sort the headlines based on time
        if (is_array($shareResults) && is_array($userResults)) {
            $allNews = array_merge($shareResults, $userResults);
        } else {
            if (is_array($shareResults)) {
                $allNews = $shareResults;
            } else {
                if (is_array($userResults)) {
                    $allNews = $userResults;
                } else {
                    return false;
                }
            }
        }

        usort($allNews, array($this, "timecmp"));

        $count = count($allNews);

        if ($limit > 0) {
            $allNews = array_slice($allNews, $offset, $limit);
        }

        return $allNews;
    }
}


?>