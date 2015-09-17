<?php

class Group extends DeprecatedModel
{
    public $cache = array();
    public $cacheUser = array();

    /* Enum in groups table
      */
    const STAFF_GROUP_TYPE = 'staff';
    const PARENT_GROUP_TYPE = 'parent';
    const STUDENT_GROUP_TYPE = 'student';
    const GUEST_GROUP_TYPE = 'guest';

    /**
     * A copy of the user notifier - it is called when a user changes.
     *
     * @var Schoolbox\UserNotifier
     */
    protected $userNotifier;

    function Group($service = HOMEPAGE_SERVICE)
    {
        $this->_table = 'userGroups';
        $this->_index = 'cid';
        $this->_service = $service;

        parent::DeprecatedModel();
    }


    /**
     * @return Schoolbox\UserNotifier
     */
    protected function getUserNotifier()
    {
        // only load this if you *actually* need it
        if (empty($this->userNotifier)) {
            $this->userNotifier = new Schoolbox\UserNotifier(new Schoolbox\Config\Loader());
        }
        return $this->userNotifier;
    }


    /**
     * Get a group by name
     *
     * @param  $name
     * @return array|bool
     */
    public function searchByName($name)
    {
        $query = "SELECT * FROM groups WHERE name LIKE \"%{$this->db->quoteForLike($name)}%\"";

        return $this->db->getAll($query);
    }

    function setService($service = HOMEPAGE_SERVICE)
    {
        $this->_service = $service;
    }


    /**
     * Determine whether this group can be subscribed to
     *
     * @param <type> $categoryId
     * @return <type>
     */
    public function canSubscribe($categoryId)
    {
        $query = "SELECT type
				   FROM userGroups
				  WHERE cid = '{$categoryId}' AND type >= '" . GROUP_TYPE_MODERATED . "'";

        $result = $this->db->getAll($query);

        /* If there are results, they are already subscribed
           */
        if ($result && count($result)) {
            return true;
        }

        return false;
    }

    /**
     * Get the array of ids for groups that can change passwords
     *
     * @param  $name
     * @return array|bool
     */
    public function canChangePassword()
    {
        $query = "SELECT id FROM groups WHERE can_change_password = 'true'";

        $allIds = $this->db->getAll($query);

        $ids = array();

        if (is_array($allIds) && count($allIds)) {
            foreach ($allIds as $currentId) {
                $ids[] = $currentId['id'];
            }
        }

        return $ids;
    }

    /**
     * Get the groups which are of the specified type, but return
     * an array of id's which is synonymous with the old conf way
     * of serializing/unserializing this result set.
     *
     * @param  $name
     * @return array
     */
    public function getGroupTypeIds($name)
    {
        $query = "SELECT id FROM groups WHERE group_type = \"{$name}\"";

        $allIds = $this->db->getAll($query);

        $ids = array();

        if (is_array($allIds) && count($allIds)) {
            foreach ($allIds as $currentId) {
                $ids[] = $currentId['id'];
            }
        }

        return $ids;
    }

    /**
     * Returns group type based on a group id
     * @param  int $groupID
     * @return string
     */
    public function getGroupType($groupID)
    {
        $query = "SELECT group_type FROM groups WHERE id = \"{$groupID}\"";
        return $this->db->getOne($query);
    }

    /**
     * Alias for getting Staff Group Type IDs
     *
     * @return array
     */
    public function getStaffGroupTypeIds()
    {
        return $this->getGroupTypeIds(Group::STAFF_GROUP_TYPE);
    }

    /**
     * Alias for getting Parent Group Type IDs
     *
     * @return array
     */
    public function getParentGroupTypeIds()
    {
        return $this->getGroupTypeIds(Group::PARENT_GROUP_TYPE);
    }

    /**
     * Alias for getting Student Group Type IDs
     *
     * @return array
     */
    public function getStudentGroupTypeIds()
    {
        return $this->getGroupTypeIds(Group::STUDENT_GROUP_TYPE);
    }

    /**
     * Alias for getting Guest Group Type IDs
     *
     * @return array
     */
    public function getGuestGroupTypeIds()
    {
        return $this->getGroupTypeIds(Group::GUEST_GROUP_TYPE);
    }

    function getAllByType($type)
    {
        $query = "SELECT name, id, userAccess.type accessType
				  FROM (category, userGroups )
				  LEFT JOIN userAccess ON cid = sid AND service = " . HOMEPAGE_SERVICE . " AND uid = '" . $_SESSION['id'] . "'
				  WHERE cid = id
				  AND category.deleted_at IS NULL
				  AND userGroups.type = '$type'";
        return $this->db->getAll($query);
    }

    function getAllUserAccess($uid)
    {
        $query = "SELECT service, luServices.name, sid, type FROM userAccess JOIN luServices ON service=luServices.id WHERE uid='$uid'";
        return $this->db->getAll($query);
    }

    function groupExists($relationID)
    {
        $query = "SELECT cid FROM userGroups WHERE cid = $relationID";
        $result = $this->db->getRow($query);
        if ($result['cid']) {
            return true;
        } else {
            return false;
        }
    }

    function groupList()
    {
        $query = "
            SELECT name, id
            FROM category, userGroups
            WHERE cid = id
            AND category.deleted_at IS NULL
            ORDER BY name
        ";
        return $this->db->getAll($query);
    }

    function createGroup($relationID, $type = 1)
    {
        $data = array(
            'cid' => $relationID,
            'type' => $type
        );
        return $this->db->insert('userGroups', $data);
    }

    function setType($relationID, $type = 1)
    {
        // change group type
        $data = array(
            'type' => $type,
            'cid' => $relationID,
        );
        $this->update($data);
    }

    /**
     * Update service types for a user.
     *
     * @param <type> $sid
     * @param <type> $uid
     * @param <type> $type
     */
    function setUserGroupType($sid, $uid, $type)
    {
        $query = "UPDATE userAccess SET type = '$type' WHERE uid = '$uid' AND service = '" . $this->_service . "' AND sid = '$sid'";
        if ($this->db->query($query)) {
            $this->getUserNotifier()->manualNotifyUpdated($uid, $_SESSION['user']);
        }
    }

    /**
     * Update sid value for a user.
     *
     * @param <type> $sid
     * @param <type> $uid
     * @param <type> $type
     */
    function setSID($previousSid, $sid, $uid)
    {
        $query = "UPDATE userAccess SET sid = '$sid' WHERE uid = '$uid' AND service = '" . $this->_service . "' AND sid = '$previousSid'";
        if ($this->db->query($query)) {
            $this->getUserNotifier()->manualNotifyUpdated($uid, $_SESSION['user']);
        }
    }

    function getOwner($relationID)
    {

        //get the owner(s) of the group
        $query = "SELECT title, firstname, lastname, uid, alt_email, local_email, username
				  FROM userAccess, user
				  WHERE uid = id AND service = " . $this->_service . " AND sid = $relationID AND type = " . FULL_ACCESS;
        return $this->db->getRow($query); //only one owner at the moment
    }

    function allowOwner($userID)
    {
        //checks if the user can become the owner of group
        if (Cfg::get('group_staff_only')) {
            return $this->isStaff($userID);
        } else {
            return true;
        }
    }

    function disbandGroup($relationID)
    {
        //delete an entire group including owner

        $query = "DELETE FROM userAccess WHERE service = " . $this->_service . " AND sid = $relationID";
        $this->db->query($query);

        $this->db->delete('userGroups', 'cid', $relationID);

        /* Delete groups calendar */
        $query = "DELETE FROM event WHERE access = 2 AND sid = '$relationID'";
        $this->db->query($query);
    }

    function getMemberList($relationID)
    {
        $query = "SELECT title, firstname, lastname, uid, synergy_id, type, group_id, local_email, alt_email, username
				  FROM userAccess, user
				  WHERE uid = id AND service = " . $this->_service . " AND sid = $relationID AND type < " . FULL_ACCESS . " AND type > " . DENY_ACCESS
            . " ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    function getWaitingList($relationID)
    {
        $query = "SELECT title, firstname, lastname, uid, synergy_id
				  FROM userAccess, user
				  WHERE service = " . $this->_service . " AND sid = $relationID AND type = " . DENY_ACCESS . " AND user.id = uid";
        return $this->db->getAll($query);
    }

    function isUser($relationID, $userID)
    {
        $query = "SELECT uid FROM userAccess WHERE service = '" . $this->_service . "' AND sid = '$relationID' AND uid = '$userID'";
        $this->db->query($query);

        if ($this->db->countRows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    function addUser($relationID, $userID, $type = READ_ACCESS)
    {
        if (!$this->isUser($relationID, $userID)) {
            $data = array(
                'uid' => $userID,
                'sid' => $relationID,
                'service' => $this->_service,
                'type' => $type,
            );
            if ($result = $this->db->insert('userAccess', $data)) {
                $this->getUserNotifier()->manualNotifyUpdated($userID, $_SESSION['user']);
            }
            return $result;
        }
        return false;
    }

    /**
     * Deletes a uid also can optionally delete a user based on its permission
     *
     * @param $relationID
     * @param $userID
     * @return resource
     */
    public function deleteUser($relationID, $userID)
    {
        $query = "
			DELETE FROM userAccess
			WHERE service = '{$this->db->quote($this->_service)}'
			AND sid = '{$this->db->quote($relationID)}'
			AND uid = '{$this->db->quote($userID)}'
		";

        if ($result = $this->db->query($query)) {
            $this->getUserNotifier()->manualNotifyUpdated($userID, $_SESSION['user']);
        }
        return $result;
    }

    /**
     * Does this user have access to this category ONLY through their children?
     *
     * If the user has read permission, are a parent and aren't directly
     * involved, then they have permission only through their children.
     *
     * @param int $category_id The category ID to check
     * @param int $user_id The user to check, or logged in user if not set
     *
     * @return bool
     */
    public function hasChildAccessOnly($category_id, $user_id = 0)
    {
        if (!$user_id) {
            $user_id = $_SESSION['id'];
        }
        if (!$user_id) {
            return true;
        }
        return $this->getPermission($category_id, $user_id) >= READ_ACCESS
        && $this->isParent($user_id)
        && !$this->involvedInGroup($user_id, $category_id);
    }

    function setUserPermission($relationID, $userID, $type = READ_ACCESS)
    {
        $this->deleteUser($relationID, $userID);
        return $this->addUser($relationID, $userID, $type);
    }

    function getPermission($relationID, $userID = 0)
    {

        // checks permissions for specified user
        // returns NO_ACCESS, READ_ACCESS, WRITE_ACCESS or FULL_ACCESS
        // default to current user
        if ($userID == 0) {
            if ($_SESSION['id']) {
                $userID = $_SESSION['id'];
            } else {
                return false;
            }
        }
        /* Cached results */
        if (isset($this->cache[$this->_service][$relationID][$userID])) {
            return $this->cache[$this->_service][$relationID][$userID];
        }

        /* Cache users aswell */
        if (!isset($this->cacheUser[$userID])) {
            $userHandler = new User;
            $this->cacheUser[$userID] = $user = $userHandler->getByID($userID);
        } else {
            $user = $this->cacheUser[$userID];
        }

        // if superuser just return highest permission type
        if ($user['su']) {
            $this->cache[$this->_service][$relationID][$userID] = FULL_ACCESS;
            return FULL_ACCESS;
        }
        // check individual access

        $query = "SELECT type FROM userAccess WHERE uid = " . $this->db->smartQuote(
            $userID
        ) . " AND ( ( service = " . $this->db->smartQuote($this->_service) . " ";

        if (in_array($this->_service, array(HOMEPAGE_SERVICE, CALENDAR_SERVICE))) {
            $query .= "AND sid = " . $this->db->smartQuote($relationID) . " ";
        }

        $query .= " ) ";

        if ($this->_service == HOMEPAGE_SERVICE) {
            $query .= "OR service = " . CMS_SERVICE . " ";
        }

        $query .= ") ORDER BY `type` DESC LIMIT 1 ";

        $permission = $this->db->getOne($query);
        $permission = (int)$permission;

        // check group access (HOMEPAGE_SERVICE only)
        if ($permission < WRITE_ACCESS && $this->_service == HOMEPAGE_SERVICE) {
            $query = "SELECT type FROM perms WHERE cid=" . $this->db->smartQuote(
                $relationID
            ) . " AND gid=" . $this->db->smartQuote($user['group_id']) . " ORDER BY type DESC";
            $groupPermission = $this->db->getOne($query);
            if ($groupPermission) {
                $permission = $groupPermission;
            }

            /* Check subjects, allow users directly involved in a subject access regardless */
            if ($permission < READ_ACCESS) {
                // Allow read access to folder 0 for all users?
                if ($relationID == 0) {
                    $permission = READ_ACCESS;
                } elseif ($this->involvedInSubject($relationID, $userID)) {
                    if ($this->isStaff($userID)) {
                        $permission = WRITE_ACCESS;
                    } else {
                        $permission = READ_ACCESS;
                    }
                }
            }
        }

        // allow staff write access to NEWS_SERVICE by default except at shore...
        if (($this->_service == NEWS_SERVICE) && $permission < WRITE_ACCESS &&
            Cfg::get('news_staff_post_enabled') == '1' && $this->isStaff($userID)
        ) {
            $permission = WRITE_ACCESS;
        }

        /* Set cache */
        $this->cache[$this->_service][$relationID][$userID] = $permission;
        return $permission;
    }

    /**
     * Synonymous for isGuest(), isStudent(), isParent() and isStaff()
     *
     * @param $userId
     * @param $type
     * @return bool
     */
    private function isUserOfType($userId, $type)
    {
        // checks if user is of $type
        // default to current user
        if ($userId > 0) {
            $userHandler = new User();
            $user = $userHandler->getByID($userId);
            $groupID = $user['group_id'];
        } else {
            if (is_numeric($_SESSION['gid'])) {
                $groupID = $_SESSION['gid'];
            } else {
                return false;
            }
        }

        if ($this->getGroupType($groupID) == $type) {
            return true;
        }

        return false;
    }

    /**
     * So there's this Guest account type nobody knew about for a
     * few thousand years that's been resurrected like The Sun God
     *
     * @param int $userID
     * @return bool
     */
    public function isGuest($userID = 0)
    {
        return $this->isUserOfType($userID, Group::GUEST_GROUP_TYPE);
    }

    /**
     * Find out if the userID belongs to a student
     *
     * @param int $userID
     * @return bool
     */
    public function isStudent($userID = 0)
    {
        return $this->isUserOfType($userID, Group::STUDENT_GROUP_TYPE);
    }

    /**
     * Find out if the userID belongs to a parent
     *
     * @param int $userID
     * @return bool
     */
    public function isParent($userID = 0)
    {
        return $this->isUserOfType($userID, Group::PARENT_GROUP_TYPE);
    }

    /**
     * Find out if the userID belongs to a staff
     *
     * @param int $userID
     * @return bool
     */
    public function isStaff($userID = 0)
    {
        return $this->isUserOfType($userID, Group::STAFF_GROUP_TYPE);
    }

    /**
     * Find out if the userID belongs to a superuser.
     *
     * @param int $userID
     * @return bool
     */
    public function isSuperuser($userID = 0)
    {

        // default to current user
        if ($userID > 0) {
            $userHandler = new User;
            $user = $userHandler->getByID($userID);

            if ($user['su']) {
                return true;
            } else {
                return false;
            }
        } else {
            return $_SESSION['su'] == 1 ? true : false;
        }
    }

    function isParentOfChild($childID)
    {
        if (is_array($_SESSION['children'])) {
            foreach ($_SESSION['children'] as $child) {
                if ($child['id'] == $childID) {
                    return true;
                }
            }
        }
        return false;
    }

    function involvedInGroup($uid, $cid)
    {
        //returns true on user being involved in group
        $query = "SELECT userAccess.type FROM userAccess, category
			WHERE userAccess.service = '" . $this->_service . "'
			AND userAccess.uid = '$uid'
			AND userAccess.sid = category.id
			AND category.deleted_at IS NULL
			AND category.id = '$cid'";
        return $this->db->getOne($query);
    }

    function involvedInSubject($code, $userId = null)
    {
        if (!isset($userId)) {
            $userId = $_SESSION['id'];
        }
        $subjects = $this->involvedInSubjects($userId);
        //returns true on user being involved in subject (case-insensitive)

        if (is_array($subjects)) {
            //$code = strtoupper($code);
            foreach ($subjects as $subject) {
                if (is_numeric($code) && $code == $subject['id']) {
                    return true;
                }
                if (strtoupper($code) == strtoupper($subject['code'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get a list of subjects $userID is involved in
     *
     * @param $userID
     * @return array
     */
    public function involvedInSubjects($userID)
    {
        //Fetch subject list
        $allFolders = array();

        if ($userID == $_SESSION['id']) {
            $allFolders = $_SESSION['subjects'];
        } else {
            $userMapper = new \Schoolbox\UserMapper();
            $user = $userMapper->findById($userID);

            $folderMapper = new \Schoolbox\Resource\CategoryMapper();

            if ($user->getExternalId()) {
                $externalUser = new External_User;
                $externalSubjectList = $externalUser->getSubjectList($user->getExternalId(), $user->getId());

                if (is_array($externalSubjectList)) {
                    foreach ($externalSubjectList as $currentSubject) {
                        try {
                            $folder = $folderMapper->findByCode($currentSubject['code']);

                            $allFolders[$folder->getId()] = array(
                                'id' => $folder->getId(),
                                'name' => $folder->getName(),
                                'parent' => $folder->getParent()->getId(),
                                'deleted_at' => $folder->getDeletedAt(),
                                'code' => $folder->getCodeString(),
                                'template' => $folder->getHomepage()->getTemplate()->getId(),
                            );
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
        }

        if (!is_array($allFolders)) {
            $allFolders = array();
        }
        return $allFolders;
    }

    /*
     * New function to make retrieving the list of user subjects easier, is deprecating involvedCategoryList
     *
     * @see Schoolbox\Resource\CategoryMapper->getForUser() for an implementation
     *      that uses the mapper classes.
     */
    function involvedInCategoryList($userID, $includeSubjectList = true, $accessType = READ_ACCESS)
    {

        $catList = array();

        if ($includeSubjectList == true && Cfg::get('external_enable')) {
            foreach ($this->involvedInSubjects($userID) as $subject) {
                // Subjects must still respect $accessType
                if ($this->getPermission($subject['id'], $userID) >= $accessType) {
                    $catList[] = $subject;
                }
            }
        }

        //get list of groups involved in (not already in $catList)
        $excluded_id_list = array();
        if (!empty($catList)) {
            foreach ($catList as $cat) {
                $excluded_id_list[] = intval($cat['id']);
            }
        }
        $exclude_part = !empty($excluded_id_list)
            ? ('AND category.id NOT IN (' . implode(',', $excluded_id_list) . ')')
            : '';
        $query = "
			SELECT
			category.name, category.id, type,
			GROUP_CONCAT(cc.code) as code
			FROM userAccess, category LEFT JOIN categoryCode cc ON category.id = categoryID AND cc.deleted_at IS NULL
			WHERE userAccess.uid = '$userID'
			AND userAccess.sid = category.id
			AND userAccess.service = '" . $this->_service . "'
			AND userAccess.type >= '$accessType'
			AND category.deleted_at IS NULL
			$exclude_part
			GROUP BY category.id
			ORDER BY category.name
		";

        $this->db->query($query);
        while ($result = $this->db->getNextArray()) {
            $catList[] = $result;
        }

        return $catList;
    }


    /*
     * Deprecated in favour of involvedInCateogoryList above...
     */
    function involvedCategoryList($id, $subjects = '', $accessType = READ_ACCESS)
    {
        //returns a list of categories that the user is involved in
        //optional accessType specifies read=1 , write=2, ownership=3 access levels

        if (is_array($subjects)) {
            foreach ($subjects as $subject) {
                //convert code to id and get name
                $query = "SELECT name, id, cc.code FROM category JOIN categoryCode cc ON id = categoryID WHERE cc.code = '" . $subject['code'] . "'";
                if (is_array($catTmp = $this->db->getRow($query))) {
                    $catList[] = $catTmp;
                }
            }
        }

        //get list of groups involved in
        $query = "SELECT category.name, category.id, type, GROUP_CONCAT(cc.code) as code
        FROM userAccess, category
        LEFT JOIN categoryCode cc ON category.id = categoryID AND cc.deleted_at IS NULL
		WHERE userAccess.uid = '$id' AND userAccess.sid = category.id AND userAccess.service = '" . $this->_service . "'
		AND userAccess.type >= '$accessType'
		AND category.deleted_at IS NULL
		GROUP BY category.id
		ORDER BY category.name";

        $this->db->query($query);
        while ($result = $this->db->getNextArray()) {
            $catList[] = $result;
        }

        return $catList;
    }

    function userSummary()
    {

        $query = "SELECT groups.id, groups.`name`, COUNT(*) total
				  FROM groups
				  JOIN `user` ON groups.id=`user`.group_id
				  GROUP BY groups.id
				  ORDER BY groups.`name`";

        $result = $this->db->query($query);

        while ($group = $this->db->getNextArray($result)) {
            $query = "SELECT COUNT(DISTINCT `user`.id) as loggedIn
						FROM login_log
						JOIN `user` ON login_log.username=`user`.username
						WHERE `date` > DATE_SUB(NOW(), INTERVAL " . (Cfg::get('session_length') * 2) . " SECOND)
						AND group_id = '{$group['id']}'
						AND login = 1";
            $loggedIn = $this->db->getOne($query);

            $summary[] = array(
                'id' => $group['id'],
                'name' => $group['name'],
                'total' => $group['total'],
                'logged_in' => $loggedIn
            );
        }

        return $summary;
    }

    public function getGroupName($groupID)
    {
        $query = "SELECT name FROM groups WHERE id = \"{$groupID}\"";
        return $this->db->getOne($query);
    }

    /**
     * Clears caches for users and groups permissions.
     */
    public function clearPermissionsCache()
    {
        $this->cache = array();
        $this->cacheUser = array();
    }
}
