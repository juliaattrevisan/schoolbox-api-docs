<?php
use Schoolbox\IHomepageCleanable;

define('FORUM_THREADS_PER_PAGE', 30);
define('FORUM_POSTS_PER_PAGE', 15);

/**
 * Class Forum
 */
class Forum extends DeprecatedModel implements IHomepageCleanable
{

    /**
     * @var string
     */
    private $_postTable;

    /**
     * Forum constructor.
     */
    public function __construct()
    {

        $this->_table     = "forumThread";
        $this->_index     = "threadID";
        $this->_postTable = "forumPost";

        parent::__construct();
    }


    /**
     * @param $categoryID
     * @param $config
     * @return array|bool
     */
    public function getRecentThreadList($categoryID, $config)
    {

        /* Set default sorting to threads, but leave options for other sort fields
         */
        if ($config['sort'] == 'Posts') {
            $sortField = 't.last_post_date';
        } elseif ($config['sort'] == 'Threads') {
            $sortField = 't.date';
        } else {
            $sortField = 't.date';
        }

        $query = "SELECT t.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname, u.synergy_id
                  FROM forumThread t
                  LEFT JOIN user u ON u.id = t.userID
                  WHERE t.categoryID = $categoryID
                  AND t.deleted = 'false'
                  ORDER BY $sortField DESC ";

        if ($config['size'] != 0 && $config['size'] != '') {
            $query .= " LIMIT 0, {$config['size']}";
        }

        return $this->db->getAll($query);
    }


    /**
     * @param     $categoryID
     * @param int $index
     * @param int $limit
     * @return array|bool
     */
    public function getThreadList($categoryID, $index = 0, $limit = 0)
    {
        $query = "SELECT t.*,u.id userID, u.title userTitle, u.firstname userFirstname, u.lastname userLastname, u.synergy_id userSynID, u.deleted_at authorDeletedAt,
                      u2.title last_post_title, u2.firstname last_post_firstname, u2.lastname last_post_surname, u2.synergy_id last_post_external_id,
                    COUNT(DISTINCT p.postID) - 1 replies
                  FROM forumThread t
                  LEFT JOIN `user` u ON u.id = t.userID
                  LEFT JOIN `user` u2 ON u2.id = t.last_post_user_id
                  LEFT JOIN forumPost p ON p.threadID = t.threadID AND p.deleted = 'false'
                  WHERE t.categoryID = $categoryID
                  AND t.deleted = 'false'
                  GROUP BY p.threadID
                  ORDER BY t.last_post_date DESC ";
        if ($limit) {
            $query .= "LIMIT $index, $limit ";
        }

        return $this->db->getAll($query);
    }


    /**
     * @param $threadID
     * @return array|bool|null
     */
    public function getThread($threadID)
    {
        $query = "SELECT t.*, p.postID, p.body FROM forumThread t, forumPost p
                  WHERE t.threadID = p.threadID AND t.threadID = $threadID AND t.deleted = 'false'
                  ORDER BY p.date ASC LIMIT 1";
        return $this->db->getRow($query);
    }


    /**
     * @param     $threadID
     * @param int $index
     * @param int $limit
     * @return array|bool
     */
    public function getPostList($threadID, $index = 0, $limit = 0)
    {
        $query = "SELECT p.*, u.synergy_id, u.title userTitle, u.firstname userFirstname, u.lastname userLastname, g.name userGroup FROM forumPost p
                  LEFT JOIN user u ON u.id = p.userID
                  LEFT JOIN groups g ON g.id = u.group_id
                  WHERE p.threadID = $threadID AND p.deleted = 'false' ";
        if ($limit) {
            $query .= "LIMIT $index, $limit ";
        }

        return $this->db->getAll($query);
    }


    /**
     * @param $postID
     * @return array|bool|null
     */
    public function getPost($postID)
    {
        $query = "SELECT p.*, u.title userTitle, u.firstname userFirstname, u.lastname userLastname
                  FROM " . $this->_postTable . " p
                  LEFT JOIN user u ON u.id = p.userID
                  WHERE postID = $postID AND p.deleted = 'false'";
        return $this->db->getRow($query);
    }


    /**
     * @param $categoryID
     * @return bool
     */
    public function hasForum($categoryID)
    {

        $query  = "SELECT ci.id FROM component_instance ci JOIN component_definition cd ON ci.`component_definition_id`=cd.`id` WHERE class = 'Component_Homepage_ForumListController' AND ci.module = 'homepage' AND identifier = $categoryID";
        $result = $this->db->getOne($query);

        if (isset($result)) {
            return true;
        }

        return false;
    }


    /**
     * @param $threadID
     */
    public function lockThread($threadID)
    {
        $data = [
            'threadID' => $threadID,
            'locked'   => 'true',
        ];

        $this->update($data);
    }


    /**
     * @param $threadID
     */
    public function uniqueView($threadID)
    {
        if (!isset($_SESSION['threads_viewed'])) {
            $_SESSION['threads_viewed'] = [];
        }

        if (!isset($_SESSION['threads_viewed'][$threadID])) {
            $query = "UPDATE " . $this->_table . " SET views = views + 1 WHERE threadID = " . $this->db->smartQuote($threadID);
            $this->db->query($query);

            $_SESSION['threads_viewed'][$threadID] = true;
        }

        return;
    }


    /**
     * @param $postID
     * @return false|float
     */
    public function getPageNumber($postID)
    {
        $query = "SELECT o.* FROM " . $this->_postTable . " p
                  LEFT JOIN " . $this->_postTable . " o ON o.threadID = p.threadID AND o.deleted = 'false'
                  WHERE p.postID = $postID AND p.deleted = 'false'";
        $i          = 0;
        $postNumber = 0;
        $postList   = $this->db->getAll($query);

        if (is_array($postList)) {
            foreach ($postList as $post) {
                $i++;

                if ($post['postID'] == $postID) {
                    $postNumber = $i;
                    break;
                }
            }
        }

        return ceil($postNumber / FORUM_POSTS_PER_PAGE);
    }


    /**
     * @param $text
     * @return string|string[]|null
     */
    public function parseBB($text)
    {
        $find    = [
            '%\[quote\](.+)\[/quote\]%',
            '%\[quote(="([^"]+)")?\](.+)\[/quote\]%',
        ];
        $replace = [
            '<blockquote><p>$1</p></blockquote>',
            '<cite>$2</cite><blockquote><p>$3</p></blockquote>',
        ];

        return preg_replace($find, $replace, html_entity_decode($text));

    }


    /**
     * @param $data
     * @return int|string
     */
    public function create($data)
    {
        $data['date'] = 'NOW()';
        return parent::create($data);
    }


    /**
     * @param $threadID
     * @return bool
     */
    public function delete($threadID)
    {
        $data = [
            'threadID' => $threadID,
            'deleted'  => 'true',
        ];
        return $this->update($data);
    }


    /**
     * @deprecated
     */
    public function createPost($data)
    {
        $data['date'] = 'NOW()';
        $this->db->insert($this->_postTable, $data);
        $data['postID'] = $this->db->insertID();


        /* update thread */
        $userHandler = new User();
        $user        = $userHandler->getByID($_SESSION['id']);

        $thread = [
            'threadID'          => $data['threadID'],
            'last_post_id'      => $data['postID'],
            'last_post_date'    => 'NOW()',
            'last_post_user_id' => $_SESSION['id'],
            'last_post_user'    => $userHandler->formatName($user),
        ];

        $this->update($thread);

        return $data['postID'];
    }


    /**
     * @deprecated
     */
    public function updatePost($data)
    {
        $data['modified'] = 'NOW()';

        unset($data['threadID']);
        unset($data['userID']);

        $current = $this->getPost($data['postID']);
        if ($current['userID'] != $_SESSION['id']) {
            $data['censored'] = 'true';
        }

        return $this->db->update($this->_postTable, 'postID', $data);
    }


    /**
     * @param $postID
     * @return bool
     */
    public function deletePost($postID)
    {
        $data = [
            'postID'  => $postID,
            'deleted' => 'true',
        ];

        return $this->updatePost($data);
    }


    /**
     * @param int $cid
     * @return PDOStatement|void
     */
    public function homepageClean($cid)
    {
        $query = "UPDATE {$this->_table}, {$this->_postTable}
            SET {$this->_table}.deleted=true, {$this->_postTable}.deleted=true
            WHERE {$this->_table}.{$this->_index} = {$this->_postTable}.{$this->_index}
            AND {$this->_table}.categoryID = $cid";

        return $this->db->query($query);
    }


    /**
     * @param        $keyword
     * @param int    $offset
     * @param int    $limit
     * @param null   $count
     * @param string $sort_by
     * @param string $sort_dir
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score_title', $sort_dir = 'desc')
    {
        $keyword       = $this->db->quote($keyword);
        $sort_by_quote = $this->db->quoteIdentifier($sort_by);

        $query = "SELECT `forumThread`.*, `forumPost`.*, `forumThread`.title AS `threadTitle`,
                            `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                            MATCH(`body`) AGAINST ('$keyword') AS `score`,
                            MATCH(`forumThread`.`title`) AGAINST ('$keyword') AS `score_title`
                        FROM `forumPost`
                        JOIN `forumThread` ON `forumPost`.threadID = `forumThread`.threadID
                        JOIN `folder` ON `folder`.id = `forumThread`.categoryID AND `folder`.deleted_At IS NULL
                        LEFT JOIN `user` ON `forumPost`.userID = `user`.id
                        LEFT JOIN `perms` ON `forumThread`.categoryID = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `forumThread`.categoryID = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND (
                                MATCH(`body`) AGAINST ('$keyword') OR
                                MATCH(`forumThread`.`title`) AGAINST ('$keyword')
                            )
                            AND `forumThread`.`deleted` = 'false'
                            AND `forumPost`.`deleted` = 'false'
                        ORDER BY {$sort_by_quote} $sort_dir, `score` $sort_dir
                        LIMIT $offset, $limit;
            ";

        $count_query = "SELECT COUNT(*)
                        FROM `forumPost`
                        JOIN `forumThread` ON `forumPost`.threadID = `forumThread`.threadID
                        JOIN `folder` ON `folder`.id = `forumThread`.categoryID AND `folder`.deleted_At IS NULL
                        LEFT JOIN `user` ON `forumPost`.userID = `user`.id
                        LEFT JOIN `perms` ON `forumThread`.categoryID = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `forumThread`.categoryID = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND (
                                MATCH(`body`) AGAINST ('$keyword') OR
                                MATCH(`forumThread`.`title`) AGAINST ('$keyword')
                            )
                            AND `forumThread`.`deleted` = 'false'
                            AND `forumPost`.`deleted` = 'false'
            ";

        $count = $this->db->getOne($count_query);

        return $this->db->getAll($query);
    }
}
