<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

//display what has change since the last login for the user
use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 * Class Activity
 */
class Activity
{
    /**
     * A database connection
     */
    private IConnector $db;


    /**
     * Activity constructor.
     */
    public function __construct()
    {
        //setup db
        $this->db = Factory::getInstanceInternal();
    }


    /**
     * @param int $id
     * @return mixed|string
     */
    public function lastCatVisit($id)
    {
        //get last visit to category
        $query  = $this->db->query(
            "SELECT datetime FROM homepage_log WHERE folder_id = ? AND user_id = ?",
            [$id, $_SESSION['user']->getId()],
        );
        $result = $query->fetch();

        if (false !== $result && $result['datetime']) {
            return $result['datetime'];
        }

        return '0000-00-00';
    }


    /**
     * @param int $id
     * @return mixed
     */
    public function newCategory($id)
    {
        $lastcatvisit = $this->lastCatVisit($id);

        //check if any new news has been submitted
        $query  = $this->db->query(
            "
                SELECT COUNT(DISTINCT comms_news_article.id) AS newscount
                FROM comms_news_article
                JOIN comms_news_audience cna on comms_news_article.id = cna.comms_news_article_id AND entity_type = 'folder'
                WHERE deleted_at IS NULL
                AND   entity_value = ?
                AND   publish_at >= ?
                AND   publish_at <= NOW()
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['news'] = $result['newscount'];

        //check if any new blogs has been submitted
        $query  = $this->db->query(
            "
                SELECT COUNT(*) AS blogcount FROM blog
                WHERE deleted_at IS NULL AND folder_id = ? AND created_at >= ?
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['blog'] = $result['blogcount'];

        //check if any new files have been submitted
        $query  = $this->db->query(
            "
                SELECT COUNT(*) AS filecount FROM file WHERE
                deleted_at IS NULL AND category = ? AND submitted >= ?
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['files'] = $result['filecount'];

        //check if any new links have been submitted
        $query  = $this->db->query(
            "
                SELECT COUNT(*) AS linkcount FROM link WHERE category = ?
                AND submitted >= ?
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['links'] = $result['linkcount'];

        //check if any new forum posts have been made
        $query  = $this->db->query(
            "
                SELECT COUNT(*) AS forumcount
                FROM forumThread INNER JOIN forumPost ON forumThread.threadID = forumPost.threadID
                WHERE forumThread.categoryID = ?
                AND   forumThread.deleted_at IS NULL
                AND   forumPost.deleted_at IS NULL
                AND   forumPost.date >= ?
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['forum'] = $result['forumcount'];

        //check if any new wiki revisions have been made
        $query  = $this->db->query(
            "
                SELECT COUNT(*) AS wikicount
                FROM wiki INNER JOIN wikiRevision ON wiki.wikiID = wikiRevision.wikiID
                WHERE wiki.categoryID = ? AND wiki.deleted != true AND wikiRevision.created >= ?
            ",
            [$id, $lastcatvisit],
        );
        $result = $query->fetch();

        $count['wiki'] = $result['wikicount'];

        return $count;
    }
}
