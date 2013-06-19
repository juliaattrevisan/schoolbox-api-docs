<?php



class Category extends DeprecatedModel {

    function Category() {

        $this->_table = "category";
        $this->_index = "id";
        $this->categoryTree = array();
        $this->nodeList = array();

        parent::DeprecatedModel();
    }

    /**
     * Get a category by name
     *
     * @param  $name
     * @return array|bool
     */
    public function searchByName($name, $parent = false) {
        $query = "SELECT category.*
				  FROM category
				  LEFT JOIN categoryCode ON category.id = categoryCode.categoryID
				  WHERE (category.name LIKE \"%{$name}%\"
				  OR categoryCode.code LIKE \"%{$name}%\")";

        if($parent != false && is_numeric($parent)) {
            $query .= " AND parent = '$parent'";
        }

        return $this->db->getAll($query);
    }

    /**
     * Get the code for a category. Uses the categoryCode table in a join, as
     * category.code is deprecated
     *
     * @param <type> $category_id
     * @return <type>
     */
    function getCodeForCategory($category_id) {
        $query = "SELECT GROUP_CONCAT(categoryCode.code) AS codes
				  FROM category
				  LEFT JOIN categoryCode ON category.id = categoryCode.categoryID
				  WHERE category.id = '$category_id'";

        return $this->db->getRow($query);
    }

    function getCategoryList($categoryID, $config = false) {

        if (is_array($categoryID)) {
            $cats = " IN ('" . implode("', '", $categoryID) . "') ";
        } else {
            $cats = " = '$categoryID' ";
        }

        $query = "SELECT id, name, GROUP_CONCAT(co.code) code, cf.template, parent,
					(SELECT 1 FROM `" . $this->_table . "` k
					 LEFT JOIN perms p ON p.cid = k.id AND p.gid = '" . $_SESSION['gid'] . "' AND p.type > 0
					 LEFT JOIN userAccess ua ON ua.uid = '" . $_SESSION['id'] . "' AND ua.service = 1 AND ua.sid = k.id AND ua.type > 0
					 WHERE k.parent = ca.id AND (p.type IS NOT NULL OR ua.type IS NOT NULL OR 1 = " . (int)$_SESSION['su'] . ") LIMIT 1) isParent
				  FROM category ca
				  LEFT JOIN categoryCode co ON co.categoryID = ca.id
				  LEFT JOIN homepage_config cf ON cf.cid = ca.id
				  WHERE parent $cats
				  GROUP BY ca.id";

        $sortFields = array('name' => 'name', 'date' => 'id');
        $config['sort'] = in_array($config['sort'], $sortFields) ? $config['sort'] : 'name';
        $config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

        $query .= " ORDER BY " . $config['sort'] . " " . $config['sortdir'];

        if ($config['size'] > 0) {
            $query .= " LIMIT 0, " . $config['size'];
        }

        return $this->db->getAll($query);
    }

    public function getSimpleCategoryList($categoryId) {
        $query = "SELECT id, name, parent, cf.template, GROUP_CONCAT(co.code) code
      				  FROM category ca
      				  LEFT JOIN categoryCode co ON co.categoryID = ca.id
      				  LEFT JOIN homepage_config cf ON cf.cid = ca.id
      				  WHERE parent = $categoryId
      				  GROUP BY ca.id
      	";

        $categoryList = $this->db->getAll($query);

        return $categoryList;
    }

    function hasChildren($categoryID) {
        $query = "SELECT c.id as children
				  FROM category c
				  WHERE parent = $categoryID
				  LIMIT 1";

        return $this->db->getOne($query);
    }

    function getParent($categoryID) {
        $query = "SELECT parent FROM category WHERE id = $categoryID";
        return $this->db->getOne($query);
    }

    function getByID($categoryID) {

        require_once dirname(__FILE__) . '/group.php';

        $query = "SELECT c.*, GROUP_CONCAT(co.code) code, u.id owner, u.title, u.firstname, u.lastname, g.type type, cf.template
				  FROM (category c)
				  LEFT JOIN categoryCode co ON co.categoryID = c.id
				  LEFT JOIN userAccess a ON a.sid = c.id AND a.type = 3 AND service = " . HOMEPAGE_SERVICE . "
				  LEFT JOIN user u on u.id = a.uid
				  LEFT JOIN userGroups g ON g.cid = c.id
				  LEFT JOIN homepage_config cf ON cf.cid = c.id
				  WHERE c.id = '$categoryID'
				  GROUP BY c.id";

        return $this->db->getRow($query);
    }

    function getByCode($code) {

        require_once dirname(__FILE__) . '/group.php';

        $query = "SELECT c.*, GROUP_CONCAT(co.code) code, u.id owner, u.title, u.firstname, u.lastname, g.type type, cf.template
                  FROM (category c)
                  LEFT JOIN categoryCode co ON co.categoryID = c.id
                  LEFT JOIN userAccess a ON a.sid = c.id AND a.type = 3 AND service = " . HOMEPAGE_SERVICE . "
                  LEFT JOIN user u on u.id = a.uid
                  LEFT JOIN userGroups g ON g.cid = c.id
                  LEFT JOIN homepage_config cf ON cf.cid = c.id
                  WHERE (co.code = {$this->db->smartQuote($code)} OR co.code LIKE {$this->db->smartQuote('%/'.$code)})
                  GROUP BY c.id";

        return $this->db->getRow($query);
    }

    function logPageView($categoryID) {
        // new mechanism for storing pageviews
        // the use of NOW() generates a truncation warning (non-fatal and non-critical) since a datetime is being
        // shoved into a date field, but it can be safely ignored: the data inserts correctly
        $stmt = Database::getInstance()->prepare(
            "
            INSERT INTO homepage_visits (`date`, `user_group_id`, `category_id`)
            VALUES (NOW(), :gid, :cid)
            ON DUPLICATE KEY
            UPDATE `hitcount` = `hitcount` + 1"
        );

        $stmt->execute(
            array(
                'cid' => $categoryID,
                'gid' => $_SESSION['gid']
            )
        );

        // old mechanism for storing pageviews - will use both in tandem for the time being
        $query = "SELECT datetime FROM homepage_log WHERE cid = '" . $categoryID . "' AND user = '" . $_SESSION['id'] . "'";
        $this->db->query($query);

        if ($row = $this->db->getNextArray()) {
            $query = "UPDATE homepage_log SET datetime = NOW(), hitcount = hitcount + 1 WHERE cid = '" . $categoryID . "'
			AND user = '" . $_SESSION['id'] . "'";
        } else {
            $query = "INSERT INTO homepage_log (datetime, user, cid,hitcount) VALUES ( NOW() ,
			'" . $_SESSION['id'] . "', '" . $categoryID . "', '0')";
        }

        $this->db->query($query);
    }

    function lastVisit($categoryID, $userID) {
        //get the time since the last visit to this homepage
        $query = "SELECT DATE_FORMAT(`datetime`, '%a %D %b %H:%i') as visit FROM homepage_log, category
				  WHERE category.id = '$categoryID'
				  AND	category.id = homepage_log.cid
				  AND   homepage_log.user = '$userID'
		";
        return $this->db->getOne($query);
    }

    function getComponents($categoryID) {

        $query = "SELECT homepageComponentLocationID, position, `column`, component, homepage_comp.config dfconfig,
                        homepage_comp_location.config, name, method, filename
				  FROM homepage_comp_location, homepage_comp
				  WHERE component = id AND cid = '" . $categoryID . "'
				  ORDER BY position";

        return $this->db->getAll($query);

    }

    function getComponent($categoryID, $componentID) {

        $query = "SELECT homepageComponentLocationID, position, `column`, component, homepage_comp.config dfconfig,
                        homepage_comp_location.config, name, method, filename
				  FROM homepage_comp_location JOIN homepage_comp ON component = id
				  WHERE cid = '" . $categoryID . "' AND component = '" . $componentID . "'
				  ORDER BY `column`, `position` LIMIT 1";
        return $this->db->getRow($query);

    }

    function getImages($categoryID, $limit = 0, $excludeWork = false) {
        $query = "SELECT id, file.description
				  FROM file
				  LEFT JOIN allowedFileTypes ON mime_type = mime
				  WHERE category = '$categoryID'
				  AND deleted = '0'
				  AND INSTR(mime_type, 'image/')
				  AND inline = 'true'";

        $query .= "ORDER BY submitted";


        if ($limit > 0) {
            $query .= " LIMIT 0, $limit";
        }

        return $this->db->getAll($query);
    }

    /**
     * @param $sourceCategoryId
     * @param $destinationCategoryId
     */
    public function migrateCategory($sourceCategoryId, $destinationCategoryId) {
        $homepageHandler = new Homepage($sourceCategoryId);
        $fileHandler = new File;
        $linkHandler = new Link;
        $textBoxHandler = new TextBox;

        $this->migratePermissionList($sourceCategoryId, $destinationCategoryId);
        $homepageHandler->migrateHomepageConfiguration($sourceCategoryId, $destinationCategoryId);
        $fileHandler->migrateCategory($sourceCategoryId, $destinationCategoryId);
        $linkHandler->migrateCategory($sourceCategoryId, $destinationCategoryId);
        $textBoxHandler->migrateCategory($sourceCategoryId, $destinationCategoryId);

        if ($sourceChildList = $this->getSimpleCategoryList($sourceCategoryId)) {
            foreach ($sourceChildList as $sourceChild) {

                /* Create destination child if it doesn't exist */
                if (!$destinationChild = $this->getByNameAndParent($sourceChild['name'], $destinationCategoryId)) {
                    $destinationChild = $sourceChild;

                    unset($destinationChild['id'], $destinationChild['code']);

                    $destinationChild['parent'] = $destinationCategoryId;

                    $destinationChild['id'] = $this->create($destinationChild);
                }

                $this->migrateCategory($sourceChild['id'], $destinationChild['id']);

            }
        }
    }

    /**
     * @param $sourceCategoryId
     * @param $destinationCategoryId
     */
    public function migratePermissionList($sourceCategoryId, $destinationCategoryId) {
        $query = "
            SELECT cid, gid, type
            FROM perms
            WHERE cid = $sourceCategoryId
        ";

        if ($permissionList = $this->db->getAll($query)) {
            $this->db->delete('perms', 'cid', $destinationCategoryId);

            foreach ($permissionList as $permission) {
                $permission['cid'] = $destinationCategoryId;

                $this->db->insert('perms', $permission);
            }
        }
    }

    function getCategoryTree($parent = 0, $indent = '') {

        $categoryList = $this->getCategoryList($parent);
        if (is_array($categoryList)) {
            foreach ($categoryList as $category) {

                $thisRow['CAT_ID'] = $category['id'];
                $thisRow['CAT_NAME'] = $indent . Scrub::text($category['name']) . ' ' . Scrub::text($category['code']);
                array_push($this->categoryTree, $thisRow);

                $this->getCategoryTree($category['id'], $indent . "&nbsp; &nbsp;|&nbsp;");
            }
        }
        return $this->categoryTree;
    }

    function getCategoryNode($categoryID = 0) {
        $categoryID = (int)$categoryID;

        // get folder contents
        $categoryList = $this->getCategoryList($categoryID);

        if ($categoryID > 0) {
            // get parent folder contents
            $siblingList = $this->getCategoryNode($this->getParent($categoryID));

            if (is_array($categoryList)) {
                // find index of parent node in siblingList
                $total = count($siblingList);
                for ($i = 0; $i < $total; $i++) {
                    if ($siblingList[$i]['id'] == $categoryID) {
                        break;
                    }
                }

                // insert folder contents next to parent node
                array_splice($siblingList, $i + 1, 0, $categoryList);
            }
            return $siblingList;
        } else {
            return $categoryList;
        }
    }

    function getGroupPerms($categoryID) {
        $query = "SELECT id, name, type FROM groups g
				  LEFT JOIN perms p ON p.gid = g.id AND p.cid = '$categoryID'
				  ORDER BY id";
        return $this->db->getAll($query);
    }

    function checkForChildren($id) {
        //check for child folders
        $query = "SELECT id FROM category WHERE parent = '" . $id . "' LIMIT 1";
        if ($this->db->getOne($query)) {
            return true;
        }


        //check for files/links in category
        $query = "SELECT id FROM link WHERE category = '" . $id . "' LIMIT 1";
        if ($this->db->getOne($query)) {
            return true;
        }

        $query = "SELECT id FROM file WHERE category = '" . $id . "' AND deleted = '0' LIMIT 1";
        if ($this->db->getOne($query)) {
            return true;
        }

        return false;
    }


    function logAction($categoryID, $action) {
        $logEntry = array(
            'object' => 'folder',
            'objectId' => $categoryID,
            'action' => $action,
            'user' => $_SESSION['id'],
        );

        $this->db->insert('cmsLog', $logEntry);
    }

    function deletePerms($cid) {
        $this->db->delete('perms', 'cid', $cid);
    }

    function insertPerm($cid, $group, $access) {
        $perm = array(
            'cid' => $cid,
            'gid' => $group,
            'type' => $access,
        );
        $this->db->insert('perms', $perm);
    }

    /**
     * Determine whether a categoryCode already exists. Accepts an array of
     * categoryCodes, or a single categoryCode string
     *
     * @param  $code
     * @param $updateID ID of element to ignore in update situation
     * @return array|bool
     */
    public function isCodeExists($codeList, $updateID = false) {
        if (empty($codeList)) {
            return false;
        }

        $query = "SELECT * FROM categoryCode WHERE  ";

        /* When updating ignore the item your updating */
        if ($updateID) {
            $query .= " categoryID != $updateID AND ";
        }

        /* Re-implode the filtered components into a query-friendly format
         */
        if (is_array($codeList) && count($codeList)) {

            foreach ($codeList as $currentCode) {
                $temp[] = '"' . $currentCode . '"';
            }

            $codeList = implode(',', $temp);

            $query .= " code IN ({$codeList}) ";
        } else {
            $query .= " code = '$codeList' ";
        }

        return $this->db->getAll($query);
    }


    public function applyPermissions($id, $group, $recursive) {

        // do you have permission to alter my permissions??

        $groupHandler = new Group();

        if ($groupHandler->getPermission($id) < WRITE_ACCESS) {
            return false;
        }

        // delete previous permission set
        $this->deletePerms($id);

        // insert new permissions
        foreach ($group as $groupID => $perm) {
            $this->insertPerm($id, $groupID, $perm);
        }

        // if doing this recursively, apply perms to all child nodes
        if ($recursive) {
            $childList = $this->getCategoryList($id);
            if (is_array($childList)) {
                /* Recurse into child folders */
                foreach ($childList as $child) {
                    $this->applyPermissions($child['id'], $group, $recursive);
                }
            }
        }
        return true;
    }


    /**
     * Create a new category
     *
     * @param  $data
     * @return int
     */
    public function create($data) {
        $group = $data['group'];
        unset($data['group']);
        unset($data['recursive']);

        /* Check if the category code exists. If it already does, spawn error;
         * otherwise continue creating. array_filter() removes empty, false
         * and null elements from the array
         */
        $codeList = explode(',', trim($data['code']));

        if (is_array($codeList) && count($codeList)) {
            $codeList = array_values(array_filter($codeList, 'trim'));
        }

        $codeExists = $this->isCodeExists($codeList);

        if ($codeExists) {
            error(136);
        }

        /* Continue normally
         */
        $categoryID = parent::create($data);

        /* Setup group permissions */
        if (is_array($group)) {
            foreach ($group as $groupID => $perm) {
                $this->insertPerm($categoryID, $groupID, $perm);
            }
        }

        if ($data['template']) {
            $homepageHandler = new Homepage($categoryID);
            $homepageHandler->createHomepageFromTemplate($data['template']);
        } else {
            // we don't want an invalid template ID going into the database
            unset($data['template']);
        }

        /* Insert synergetic subject code */
        $codeList = explode(",", $data['code']);
        if (is_array($codeList)) {
            $codeList = array_map('trim', $codeList);
            $codeList = array_unique($codeList);
            foreach ($codeList as $code) {
                if ($code) {
                    $this->db->insert('categoryCode', array('categoryID' => $categoryID, 'code' => $code));
                }
            }
        }

        $this->logAction($categoryID, 'create');
        return $categoryID;
    }

    /**
     * Update category
     *
     * @param  $data
     * @return void
     */
    public function update($data) {

        /* Check if the category code exists. If it already does, spawn error;
         * otherwise continue creating. array_filter() removes empty, false
         * and null elements from the array
         */
        $codeList = explode(',', trim($data['code']));

        if (is_array($codeList) && count($codeList)) {
            $codeList = array_values(array_filter($codeList, 'trim'));
        }

        $codeExists = $this->isCodeExists($codeList, $data['id']);

        if ($codeExists) {
            error(136);
        }

        /* Redo group permissions */
        if (is_array($data['group'])) {
            // apply permissions, recursively or not depending on 3rd variable
            $this->applyPermissions($data['id'], $data['group'], $data['recursive']);
        }

        /* Setup homepage template */
        $homepageHandler = new Homepage($data['id']);
        $homepageHandler->resetHomepageToTemplate($data['template']);

        // clear extraneous data
        unset($data['group']);
        unset($data['recursive']);
        unset($data['template']);

        /* Insert synergetic subject code */
        if (isset($data['code'])) {
            $this->db->delete('categoryCode', 'categoryID', $data['id']);

            $codeList = explode(",", $data['code']);

            if (is_array($codeList)) {
                $codeList = array_map('trim', $codeList);
                $codeList = array_unique($codeList);

                foreach ($codeList as $code) {
                    if ($code) {
                        $this->db->insert('categoryCode', array('categoryID' => $data['id'], 'code' => $code));
                    }
                }
            }
        }

        parent::update($data);

        $this->logAction($data[$this->_index], 'modify');
    }


    function delete($categoryID) {

        /* Check its empty before deleting */
        if ($this->checkForChildren($categoryID)) {
            error(12);
        }

        $this->deletePerms($categoryID);
        $this->db->delete('categoryCode', 'categoryID', $categoryID);


        // locate all the component instances associated with this homepage, and clear them out
        $cim = Component_InstanceMapper::getInstance();
        $collection = $cim->findByColumnList(array('module' => 'homepage', 'identifier' => $categoryID));
        $cim->delete($collection);

        /* Delete group */
        require_once dirname(__FILE__) . '/group.php';
        $groupHandler = new Group();
        $groupHandler->disbandGroup($categoryID);

        $this->logAction($categoryID, 'delete');

        parent::delete($categoryID);
    }

    function recursiveDelete($categoryID, $deleteSelf = true) {

        require_once dirname(__FILE__) . '/file.php';
        require_once dirname(__FILE__) . '/link.php';
        require_once dirname(__FILE__) . '/group.php';

        $groupHandler = new Group;

        if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS) {
            error(6);
        }

        $childList = $this->getCategoryList($categoryID);

        if (is_array($childList)) {
            /* Recurse into child folders */
            foreach ($childList as $child) {
                $this->recursiveDelete($child['id']);
            }
        }

        /* Delete all files */
        $fileHandler = new File;
        $fileList = $fileHandler->getAllInCategory($categoryID);
        if (is_array($fileList)) {
            foreach ($fileList as $file) {
                $fileHandler->delete($file['id']);
            }
        }

        /* Delete all links */
        $linkHandler = new Link;
        $linkList = $linkHandler->getAllInCategory($categoryID);
        if (is_array($linkList)) {
            foreach ($linkList as $link) {
                $linkHandler->delete($link['id']);
            }
        }

        /* Delete folder */
        if ($deleteSelf) {
            $this->delete($categoryID);
        }
    }

    /**
     * Search for a category. Now uses the normalised categoryCode table
     *
     * @param $keyword
     * @param int $offset
     * @param int $limit
     * @param null $count
     * @param string $sort_by
     * @param string $sort_dir
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null, $sort_by = 'id', $sort_dir = 'desc') {
        $keyword = mysql_real_escape_string($keyword);

        $query = "SELECT SQL_CALC_FOUND_ROWS `category`.*, GROUP_CONCAT(categoryCode.code) as code,
						MATCH(`name`) AGAINST (\"*$keyword*\" IN BOOLEAN MODE) AS `score`,
						MATCH(`categoryCode`.code) AGAINST (\"*$keyword*\" IN BOOLEAN MODE) AS `cscore`,
						`perms`.type AS perms_type,
						`userAccess`.type AS userAccess_type
					FROM `category`
					LEFT JOIN `perms` ON `category`.id = `perms`.cid
						AND `perms`.gid = {$_SESSION['gid']}
						AND `perms`.type > 0
					LEFT JOIN `userAccess` ON `category`.id = `userAccess`.sid
						AND `userAccess`.service = 1
						AND `userAccess`.uid = {$_SESSION['id']}
					LEFT JOIN categoryCode ON category.id = categoryCode.categoryID
					WHERE (`perms`.cid OR `userAccess`.sid)
						AND (
							MATCH(`name`) AGAINST (\"*$keyword*\" IN BOOLEAN MODE)
							OR MATCH(`categoryCode`.code) AGAINST (\"*$keyword*\" IN BOOLEAN MODE)
						)
					GROUP BY category.id
					ORDER BY `$sort_by` $sort_dir
					LIMIT $offset, $limit;
		";

        $count_query = "SELECT FOUND_ROWS()";

        $result = $this->db->getAll($query);
        $count = $this->db->getOne($count_query);

        return $result;
    }

    /**
     * Creates a breadcrumb of a category's heirarchical navigation
     *
     * @param $id
     * @return array
     */
    function breadcrumb($id) {
        $breadcrumb = array();

        // get the breadcrumb back up to the root directory
        while ($id != 0) {
            $category = $this->getByID($id);
            $id = $category['parent'];

            // recursion! something in the DB is borked! TODO: some form of logging or aux checks
            if (in_array($category, $breadcrumb)) {
                break;
            } else {
                $breadcrumb[] = $category;
            }
        }
        return array_reverse($breadcrumb);
    }

    public function getByNameAndParent($name, $parentId) {
        $query = "SELECT id, name, parent, cf.template, GROUP_CONCAT(co.code) code
      				  FROM category ca
      				  LEFT JOIN categoryCode co ON co.categoryID = ca.id
      				  LEFT JOIN homepage_config cf ON cf.cid = ca.id
      				  WHERE parent = $parentId
      				    AND name = '$name'
      				  GROUP BY ca.id
      				  LIMIT 1
      	";

        $category = $this->db->getRow($query);

        return is_numeric($category['id']) ? $category : false;
    }

}

?>
