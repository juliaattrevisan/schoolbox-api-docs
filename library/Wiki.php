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
     * @inheritDoc
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
     * @param int $wikiID
     * @return array|bool
     */
    public function getLatestRevision($wikiID)
    {
        $query = 'SELECT latestRevision FROM wiki WHERE wikiID = ?';

        $latestRevisionID = $this->db->query($query, [$wikiID])->fetchColumn();

        return $this->getByID($wikiID, $latestRevisionID);
    }


    /**
     * Gets a wiki by its id
     *
     * @param int $wikiID
     * @param int $revisionID
     * @return array|bool
     */
    public function getByID($wikiID, $revisionID = 0)
    {
        $query = "
            SELECT r.*, w.categoryID, w.locked, w.slug, UNIX_TIMESTAMP(w.created) created, UNIX_TIMESTAMP(r.created) modified, latestRevision,
                u.title, u.firstname, u.preferred_name, u.lastname, r.userID authorID
            FROM " . $this->_revisionTable . " r
            LEFT JOIN " . $this->_table . " w ON w.wikiID = r.wikiID
            LEFT JOIN user u ON u.id = r.userID
            WHERE w.deleted_at IS NULL AND r.wikiID = ?
        ";
        $params = [$wikiID];

        if ($revisionID > 0 && is_numeric($revisionID)) {
            $query .= "AND r.revisionID = ? ";
            $params[] = $revisionID;
        }

        $query .= "ORDER BY r.created DESC LIMIT 1";

        return $this->db->query($query, $params)->fetch();
    }


    /**
     * @deprecated use \Schoolbox\Wiki\RevisionMapper::findByColumn('wikiID')
     * @param int $wikiID
     * @return array|bool
     */
    public function getRevisionList($wikiID)
    {
        $query = "SELECT r.*, UNIX_TIMESTAMP(r.created) created, u.title, u.firstname, u.preferred_name, u.lastname,
                  if (u.pronoun, concat('(', pronoun.subjective, '/', pronoun.objective, ')'), '') as pronoun
                  FROM " . $this->_revisionTable . " r
                  LEFT JOIN user u ON u.id = r.userID
                  LEFT JOIN pronoun ON u.pronoun = pronoun.id
                  WHERE wikiID = ?
                  ORDER BY r.created DESC";
        return $this->db->query($query, [$wikiID])->fetchAll();
    }


    /**
     * Get a wiki for a category
     *
     * @param int    $categoryID
     * @param string $orderBy
     * @param string $sortDir
     * @param int   $limit
     * @return array
     *@deprecated use \Schoolbox\Wiki\WikiMapper::findByColumn('categoryID')
          */
    public function getByCategory($categoryID, $orderBy = 'created', $sortDir = 'Desc', $limit = 10): array
    {
        /* Adjust the table in which to sort -_-
         */
        if (($orderBy == 'created') || !strlen($orderBy)) {
            $orderBySQL = 'ORDER BY w.created';
        } else if ($orderBy  == 'modified') {
            $orderBySQL = 'ORDER BY r.created' ;
        } else {
            $orderBySQL = 'ORDER BY ' . $orderBy;
        }

        $query = "SELECT r.*, UNIX_TIMESTAMP(r.created) modified, u.title, u.firstname, u.preferred_name, u.lastname,
                  w.locked, w.categoryID, w.slug
                  FROM wiki w
                  LEFT JOIN " . $this->_revisionTable . " r ON r.revisionID = (SELECT r.revisionID FROM " . $this->_revisionTable . " r WHERE r.wikiID = w.wikiID ORDER BY r.created DESC LIMIT 1)
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE w.deleted_at IS NULL AND categoryID = ?
                  {$orderBySQL} {$sortDir}";

        if (is_numeric($limit) && ($limit > 0)) {
            $query .= " LIMIT {$limit}";
        }

        return $this->db->query($query, [$categoryID])->fetchAll();
    }


    /**
     * @param int $cid
     */
    public function homepageClean($cid)
    {
        $query = "UPDATE wiki SET deleted_AT = ? WHERE categoryID=?";
        $this->db->query($query, [Alaress_Date_Format::now()->getDatabase(), $cid]);
    }


    /**
     * @deprecated use \Schoolbox\Wiki\RevisionMapper::save()
     * @param array $data
     * @return int|string
     */
    public function createRevision($data)
    {
        $data['created'] = Alaress_Date_Format::now()->getDatabaseDate();
        $data['userID']  = $_SESSION['id'];
        $this->db->insert($this->_revisionTable, $data);
        return $this->db->lastInsertId();
    }


    /**
     * Updates a wiki's data with the latest revision if applicable
     * Note that wikis now know whether they are stubs or not
     *
     * @deprecated use \Schoolbox\Wiki\WikiMapper::save()
     * @param array $data
     * @return void
     */
    public function update($data)
    {
        $coreFields = array('categoryID', 'locked', 'slug');

        $coreData = null;
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
     * @param int $wikiID
     * @param int $revisionID
     * @return bool|int
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
     * @param int $wikiID
     * @return bool
     */
    public function delete($wikiID)
    {
        $data = array('wikiID' => $wikiID,
                      'deleted_at' => Alaress_Date_Format::now()->getDatabase());
        return parent::update($data);
    }

    /**
     * Turns all stub links red
     *
     * @param string $body
     * @return mixed
     */
    public function activateStubLinks($body)
    {
        $allMatches = array();

        $pattern = "/<a\ href=['\"]+\/wiki\/(\d+)\/([a-zA-Z0-9\-_]+)['\"]+>/";

        preg_match_all($pattern, $body, $allMatches, PREG_SET_ORDER);

        $n = \count($allMatches);

        for ($i = 0; $i < $n; $i++) {
            $currentMatch = $allMatches[$i];

            $wiki = $this->getByCategoryAndSlug($currentMatch[1], $currentMatch[2]);

            /* Append a link class to change link colour
             */
            if (!$wiki) {
                $link = str_replace('<a', '<a class="red"', $allMatches[$i][0]);
                $body = str_replace($allMatches[$i][0], $link, $body);
            }
        }

        return $body;
    }


    /**
     * Gets a wiki by its category id and its slug.
     * This can also be used to check for uniqueness
     *
     * @param int $categoryID
     * @param string $slug
     * @param int $revisionID
     * @return array|bool
     */
    public function getByCategoryAndSlug($categoryID, $slug, $revisionID = 0)
    {
        $query = "SELECT r.*,
                    w.categoryID, w.locked, UNIX_TIMESTAMP(w.created) created, w.slug,
                    UNIX_TIMESTAMP(r.created) modified, latestRevision, r.userID authorID,
                    u.title, u.firstname, u.preferred_name, u.lastname
                  FROM {$this->_revisionTable} r
                  LEFT JOIN {$this->_table} w ON w.wikiID = r.wikiID
                  LEFT JOIN user u ON u.id = r.userID
                  WHERE w.deleted_at IS NULL
                  AND w.categoryID = ?
                  AND w.slug = ?";
        $params = [$categoryID, $slug];

        if ($revisionID > 0 && is_numeric($revisionID)) {
            $query .= "AND r.revisionID = ? ";
            $params[] = $revisionID;
        }

        $query .= "ORDER BY r.created DESC LIMIT 1";

        return $this->db->query($query, $params)->fetch();
    }
}
