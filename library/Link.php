<?php

class Link extends DeprecatedModel
{

    function Link()
    {

        $this->_table = "link";
        $this->_index = "id";

        parent::DeprecatedModel();
    }

    function getByID($linkID)
    {
        $query = "SELECT l.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName,
						UNIX_TIMESTAMP(l.submitted) submitted, c.name categoryName, c.id cid
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  LEFT JOIN category c ON c.id = l.category
				  WHERE l.id = $linkID";
        return $this->db->getRow($query);
    }


    function getAllInCategory($categoryID, $config = false)
    {

        require_once dirname(__FILE__) . '/group.php';
        $groupHandler = new Group;

        if (is_array($categoryID)) {
            $cats = " IN ('" . implode("', '", $categoryID) . "') ";
        } else
            $cats = " = '$categoryID' ";

        $query = "SELECT l.*, u.firstname, u.lastname, u.title, UNIX_TIMESTAMP(submitted) submitted, 'html_alt1' iconFile
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE category $cats";

        if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS)
            $query .= " AND l.hidden = 0";

        $sortFields = array('Name' => 'l.description', 'Date' => 'l.submitted');
        $config['sort'] = isset($sortFields[$config['sort']]) ? $config['sort'] : 'Name';
        $config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

        $query .= " ORDER BY " . $sortFields[$config['sort']] . " " . $config['sortdir'];

        if ($config['size'] > 0)
            $query .= " LIMIT 0, " . $config['size'];

        return $this->db->getAll($query);
    }

    public function migrateCategory($sourceCategoryId, $destinationCategoryId)
    {
        $linkList = $this->getAllByField('category', $sourceCategoryId);

        if (is_array($linkList)) {
            foreach ($linkList as &$link) {
                unset($link['id']);

                $link['category'] = $destinationCategoryId;

                $link['id'] = parent::create($link);
            }
        }
    }

    function appendHTTP($url)
    {
        if (strncasecmp($url, "http://", 7) && strncasecmp($url, "https://", 8) && strncasecmp($url, "ftp://", 6))
            $url = "http://" . $url;
        return $url;
    }

    function logAction($linkID, $action)
    {
        $logEntry = array('object' => 'link',
            'objectId' => $linkID,
            'action' => $action,
            'user' => $_SESSION['id'],
        );

        $this->db->insert('cmsLog', $logEntry);
    }

    function update($data)
    {

        if (isset($data['url']))
            $data['url'] = $this->appendHTTP(trim($data['url']));

        $data['modified'] = 'NOW()';

        parent::update($data);

        /* Update log entry */
        $this->logAction($data[$this->_index], 'modify');

    }

    function create($data)
    {

        $data['submitted'] = 'NOW()';
        $data['modified'] = 'NOW()';
        $data['url'] = $this->appendHTTP(trim($data['url']));

        $linkID = parent::create($data);

        /* Insert log entry */
        $this->logAction($linkID, 'create');

        return $linkID;
    }

    function delete($id)
    {

        parent::delete($id);

        /* Delete log entry */
        $this->logAction($id, 'delete');

    }

    function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'score', $sort_dir = 'desc')
    {
        $keyword = mysql_real_escape_string($keyword);

        $query = "SELECT `link`.*, `user`.id AS `uid`, `user`.title, `user`.firstname, `user`.lastname,
                            MATCH(`url`,`description`,`comment`) AGAINST ('$keyword') AS `score`
                        FROM `link`
                        LEFT JOIN `user` ON `link`.owner = `user`.id
                        LEFT JOIN `perms` ON `link`.category = `perms`.cid
                            AND `perms`.gid = {$_SESSION['gid']}
                            AND `perms`.type > 0
                        LEFT JOIN `userAccess` ON `link`.category = `userAccess`.sid
                            AND `userAccess`.service = 1
                            AND `userAccess`.uid = {$_SESSION['id']}
                        WHERE (`perms`.cid OR `userAccess`.sid)
                            AND MATCH(`url`,`description`,`comment`) AGAINST ('$keyword')
                            AND `hidden` = FALSE
                        ORDER BY `$sort_by` $sort_dir
                        LIMIT $offset, $limit;
            ";

        $count_query = "SELECT COUNT(*)
                            FROM `link`
                            LEFT JOIN `user` ON `link`.owner = `user`.id
                            LEFT JOIN `perms` ON `link`.category = `perms`.cid
                                AND `perms`.gid = {$_SESSION['gid']}
                                AND `perms`.type > 0
                            LEFT JOIN `userAccess` ON `link`.category = `userAccess`.sid
                                AND `userAccess`.service = 1
                                AND `userAccess`.uid = {$_SESSION['id']}
                            WHERE (`perms`.cid OR `userAccess`.sid)
                                AND MATCH(`url`,`description`,`comment`) AGAINST ('$keyword')
                                AND `hidden` = FALSE
            ";

        $count = $this->db->getOne($count_query);

        return $this->db->getAll($query);
    }

    /**
     * Soft-deletes content
     *
     * TODO: Refactor
     *
     * @param  $cid
     * @return void
     */
    public function homepageClean($cid)
    {
        $query = "DELETE FROM {$this->_table} WHERE category = '{$cid}'";

        $this->db->query($query);
    }
}
