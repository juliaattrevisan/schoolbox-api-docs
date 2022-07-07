<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

//display what has change since the last login for the user

/**
 * Class Activity
 */
class Activity
{

    /**
     * @var DeprecatedDatabase
     */
    private $db;


    /**
     * Activity constructor.
     */
    public function __construct()
    {
        //setup db
        $this->db = DeprecatedDatabase::getInstance();
    }


    /**
     * @param int $id
     * @return mixed|string
     */
    public function lastCatVisit($id)
    {
        //get last visit to category
        $query  = "SELECT datetime FROM homepage_log WHERE folder_id = '$id' AND user_id = '" . $_SESSION['id'] . "'";
        $result = $this->db->getRow($query);

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
        $query  = "
            SELECT COUNT(DISTINCT comms_news_article.id) AS newscount
            FROM comms_news_article
            JOIN comms_news_audience cna on comms_news_article.id = cna.comms_news_article_id AND entity_type = 'folder'
            WHERE deleted_at IS NULL
            AND   entity_value = '$id'
            AND   publish_at >= '$lastcatvisit'
            AND   publish_at <= NOW()
        ";
        $result = $this->db->getRow($query);

        $count['news'] = $result['newscount'];

        //check if any new blogs has been submitted
        $query  = "SELECT COUNT(*) AS blogcount FROM blog
                  WHERE deleted_at IS NULL AND folder_id = $id AND created_at >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['blog'] = $result['blogcount'];

        //check if any new files have been submitted
        $query  = "SELECT COUNT(*) AS filecount FROM file WHERE
            deleted_at IS NULL AND category = $id AND submitted >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['files'] = $result['filecount'];

        //check if any new links have been submitted
        $query  = "SELECT COUNT(*) AS linkcount FROM link WHERE category = $id
            AND submitted >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['links'] = $result['linkcount'];

        //check if any new forum posts have been made
        $query  = "SELECT COUNT(*) AS forumcount
            FROM forumThread INNER JOIN forumPost ON forumThread.threadID = forumPost.threadID
            WHERE forumThread.categoryID = $id AND forumThread.deleted != true AND forumPost.deleted != true AND forumPost.date >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['forum'] = $result['forumcount'];

        //check if any new wiki revisions have been made
        $query  = "SELECT COUNT(*) AS wikicount
            FROM wiki INNER JOIN wikiRevision ON wiki.wikiID = wikiRevision.wikiID
            WHERE wiki.categoryID = $id AND wiki.deleted != true AND wikiRevision.created >= '$lastcatvisit'";
        $result = $this->db->getRow($query);

        $count['wiki'] = $result['wikicount'];

        return $count;
    }
}
