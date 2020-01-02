<?php

/**
 * Class Wiki
 */
class Wiki extends DeprecatedModel implements \Schoolbox\IHomepageCleanable
{
    /**
     * @var string
     */
    private $_revisionTable;


    /**
     *
     */
    public function __construct()
    {
        $this->_table = 'wiki';
        $this->_index = 'wikiID';
        $this->_revisionTable = 'wikiRevision';

        parent::__construct();
    }


    /**
     * Gets the latest revision record for the specified wiki.
     * @deprecated use \Schoolbox\Wiki\Wiki::getLatestRevision()
     *
     * @param $wikiID
     * @return array|bool
     */
    public function getLatestRevision($wikiID)
    {
        $query = 'SELECT latestRevision FROM ' . $this->_table . " WHERE wikiID = '{$this->db->quote($wikiID)}'";

        $latestRevisionID = $this->db->getOne($query);

        return $this->getByID($wikiID, $latestRevisionID);
    }


    /**
     * Gets a wiki by its id
     *
     * @param  $wikiID
     * @param int $revisionID
     * @return array|bool
     */
    public function getByID($wikiID, $revisionID = 0) {
        $query = "SELECT r.*, w.categoryID, w.locked, w.slug, UNIX_TIMESTAMP(w.created) created, UNIX_TIMESTAMP(r.created) modified, latestRevision,
                  u.title, u.firstname, u.lastname, r.userID authorID
                  FROM " . $this->_revisionTable . " r
                  LEFT JOIN " . $this->_table . " w ON w.wikiID = r.wikiID
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE w.deleted = 'false' AND r.wikiID = '{$this->db->quote($wikiID)}' ";

        if ($revisionID > 0 && is_numeric($revisionID)) {
            $query .= "AND r.revisionID = '{$this->db->quote($revisionID)}' ";
        }

        $query .= "ORDER BY r.created DESC LIMIT 1";

        return $this->db->getRow($query);
    }


    /**
     * @deprecated use \Schoolbox\Wiki\RevisionMapper::findByColumn('wikiID')
     * @param $wikiID
     * @return array|bool
     */
    public function getRevisionList($wikiID)
    {
        $query = "SELECT r.*, UNIX_TIMESTAMP(r.created) created, u.title, u.firstname, u.lastname
                  FROM " . $this->_revisionTable . " r
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE wikiID = '{$this->db->quote($wikiID)}'
                  ORDER BY r.created DESC";
        return $this->db->getAll($query);
    }


    /**
     * Get a wiki for a category
     *
     * @deprecated use \Schoolbox\Wiki\WikiMapper::findByColumn('categoryID')
     * @param        $categoryID
     * @param string $orderBy
     * @param string $sortDir
     * @param bool   $limit
     * @return array|bool
     */
    public function getByCategory($categoryID, $orderBy = 'created', $sortDir = 'Desc', $limit = false) {

        /* Adjust the table in which to sort -_-
         */
        if (($orderBy == 'created') || !strlen($orderBy)) {
            $orderBySQL = 'ORDER BY w.created';
        }
        else if($orderBy  == 'modified') {
            $orderBySQL = 'ORDER BY r.created' ;
        }else{
            $orderBySQL = 'ORDER BY '.$orderBy;
        }

        $query = "SELECT r.*, UNIX_TIMESTAMP(r.created) modified, u.title, u.firstname, u.lastname,
                  w.locked, w.categoryID, w.slug
                  FROM wiki w
                  LEFT JOIN " . $this->_revisionTable . " r ON r.revisionID = (SELECT r.revisionID FROM " . $this->_revisionTable . " r WHERE r.wikiID = w.wikiID ORDER BY r.created DESC LIMIT 1)
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE w.deleted = 'false' AND categoryID = '{$this->db->quote((string)$categoryID)}'
                  {$orderBySQL} {$sortDir}";

        if (is_numeric($limit) && ($limit > 0)) {
            $query .= " LIMIT {$this->db->quote($limit)}";
        }

        return $this->db->getAll($query);
    }


    /**
     * @param int $cid
     */
    public function homepageClean($cid)
    {
        $query = "UPDATE {$this->_table} SET deleted = true WHERE categoryID='{$this->db->quote($cid)}'";
        $this->db->query($query);
    }


    /**
     * @deprecated use \Schoolbox\Wiki\RevisionMapper::save()
     * @param $data
     * @return int|string
     */
    public function createRevision($data)
    {
        $data['created'] = 'NOW()';
        $data['userID'] = $_SESSION['id'];
        $this->db->insert($this->_revisionTable, $data);
        return $this->db->insertID();
    }


    /**
     * Updates a wiki's data with the latest revision if applicable
     * Note that wikis now know whether they are stubs or not
     *
     * @deprecated use \Schoolbox\Wiki\WikiMapper::save()
     * @param  $data
     * @return void
     */
    public function update($data) {
        $coreFields = array('categoryID', 'locked', 'slug');

        foreach ($coreFields as $field) {
            if ($data[$field]) {
                $coreData[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        //Update fields in core table
        if (is_array($coreData)) {
            $coreData['wikiID'] = $data['wikiID'];
            parent::update($coreData);
        }

        //Otherwise update revisions if more than just the wikiID is left
        if (count($data) > 1) {
            $revisionID = $this->createRevision($data);

            parent::update(array(
                'wikiID' => $data['wikiID'],
                'latestRevision' => $revisionID,
            ));
        }
    }


    /**
     * @param $wikiID
     * @param $revisionID
     * @return bool|int|string
     */
    public function revert($wikiID, $revisionID)
    {
        $wiki = $this->getByID($wikiID, $revisionID);
        if (!isset($wiki['wikiID'])) {
            return false;
        }

        $data = array('wikiID' => $wiki['wikiID'],
                      'heading' => $wiki['heading'],
                      'body' => $wiki['body']);

        $revisionID = $this->createRevision($data);
        parent::update(array('wikiID' => $data['wikiID'], 'latestRevision' => $revisionID));

        return $revisionID;
    }


    /**
     * @deprecated use \Schoolbox\Wiki\WikiMapper::delete()
     * @param $wikiID
     * @return bool
     */
    public function delete($wikiID)
    {
        $data = array('wikiID' => $wikiID,
                      'deleted' => 'true');
        return parent::update($data);
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
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc')
    {
        /** @var \Schoolbox\User $user */
        $keyword  = $this->db->quote($keyword);
        $user     = $_SESSION['user'];
        $userId   = $this->db->quote($user->getId());
        $roleId   = $this->db->quote($user->getRole()->getId());
        $offset   = intval($offset);
        $limit    = intval($limit);
        $sort_dir = (strtolower($sort_dir) === 'desc' ? 'desc' : 'asc');

        $query = "SELECT wiki.*, wikiRevision.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                            MATCH(`heading`,`body`) AGAINST ('$keyword') AS `score`
                        FROM `wikiRevision`
                        LEFT JOIN `wiki` ON `wikiRevision`.wikiID = `wiki`.wikiID AND `wikiRevision`.revisionID = `wiki`.latestRevision
                        JOIN `folder` ON `folder`.id = `wiki`.categoryID AND `folder`.deleted_at IS NULL
                        LEFT JOIN `user` ON `wikiRevision`.userID = `user`.id
                        LEFT JOIN `perms` ON `wiki`.categoryID = `perms`.cid
                            AND `perms`.gid = '{$roleId}'
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `wiki`.categoryID = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = '{$userId}'
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                            AND deleted = 'false'
                        ORDER BY {$this->db->quoteIdentifier($sort_by)} $sort_dir
                        LIMIT $offset, $limit;
            ";

        $count_query = "SELECT COUNT(*)
                        FROM `wikiRevision`
                        LEFT JOIN `wiki` ON `wikiRevision`.wikiID = `wiki`.wikiID AND `wikiRevision`.revisionID = `wiki`.latestRevision
                        JOIN `folder` ON `folder`.id = `wiki`.categoryID AND `folder`.deleted_at IS NULL
                        LEFT JOIN `user` ON `wikiRevision`.userID = `user`.id
                        LEFT JOIN `perms` ON `wiki`.categoryID = `perms`.cid
                            AND `perms`.gid = '{$roleId}'
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `wiki`.categoryID = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = '{$userId}'
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`heading`,`body`) AGAINST ('$keyword')
                            AND `deleted` = 'false'
            ";

        $count = $this->db->getOne($count_query);

        return $this->db->getAll($query);
    }


    /**
     * Get a wiki ID for the prescribed heading.
     *
     * @deprecated use \Schoolbox\Wiki\RevisionMapper::findOneByColumn('heading')
     * @param  $heading
     * @return array|bool
     */
    public function getWikiByHeading($heading) {
        $query = "SELECT wiki.wikiID FROM wiki
                    LEFT JOIN wikiRevision ON wiki.latestRevision = wikiRevision.revisionID
                    WHERE heading = \"{$this->db->quote($heading)}\"";

        return $this->db->getRow($query);
    }


    /**
     * Turns all stub links red
     *
     * @param  $body
     * @return mixed
     */
    public function activateStubLinks($body) {
        $allMatches = array();

        $pattern = "/<a\ href=['\"]+\/wiki\/(\d+)\/([a-zA-Z0-9\-_]+)['\"]+>/";

        preg_match_all($pattern, $body, $allMatches, PREG_SET_ORDER);

        if (count($allMatches)) {
            for ($i=0; $i < count($allMatches); $i++) {
                $currentMatch = $allMatches[$i];

                $wiki = $this->getByCategoryAndSlug($currentMatch[1], $currentMatch[2]);

                /* Append a link class to change link colour
                 */
                if (!$wiki) {
                    $link = str_replace('<a', '<a class="red"', $allMatches[$i][0]);
                    $body = str_replace($allMatches[$i][0], $link, $body);
                }
            }
        }

        return $body;
    }


    /**
     * Gets a wiki by its category id and its slug.
     * This can also be used to check for uniqueness
     *
     * @param     $categoryID
     * @param     $slug
     * @param int $revisionID
     * @return array|bool
     */
    public function getByCategoryAndSlug($categoryID, $slug, $revisionID = 0) {
        $query = "SELECT r.*,
                    w.categoryID, w.locked, UNIX_TIMESTAMP(w.created) created, w.slug,
                    UNIX_TIMESTAMP(r.created) modified, latestRevision, r.userID authorID,
                    u.title, u.firstname, u.lastname
                  FROM {$this->_revisionTable} r
                  LEFT JOIN {$this->_table} w ON w.wikiID = r.wikiID
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE w.deleted = 'false'
                  AND w.categoryID = '{$this->db->quote($categoryID)}'
                  AND w.slug = '{$this->db->quote($slug)}'";

        if ($revisionID > 0 && is_numeric($revisionID)) {
            $query .= "AND r.revisionID = '{$this->db->quote($revisionID)}' ";
        }

        $query .= "ORDER BY r.created DESC LIMIT 1";

        return $this->db->getRow($query);
    }
}
