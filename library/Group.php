<?php

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use \Schoolbox\User as UserModel;
use \Schoolbox\UserMapper;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Permission\Access\Access;

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

    /**
     * The service for which this group object is for.
     *
     * @var int
     */
    private $_service;

    function __construct($service = UserAccess::SERVICE_HOMEPAGE)
    {
        $this->_table = 'userGroups';
        $this->_index = 'cid';
        $this->_service = $service;

        parent::__construct();
    }


    /**
     * @return Schoolbox\UserNotifier
     */
    protected function getUserNotifier()
    {
        // only load this if you *actually* need it
        if (empty($this->userNotifier)) {
            // TODO SA-8354: audit all places this could be used: replace with mapper
            $this->userNotifier = new Schoolbox\UserNotifier(new Schoolbox\Config\Loader());
        }
        return $this->userNotifier;
    }


    function setService($service = UserAccess::SERVICE_HOMEPAGE)
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
				  WHERE cid = '{$categoryId}' AND type >= '" . \Schoolbox\Resource\Group::MODERATED_GROUP . "'";

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
        return $this->getGroupTypeIds(Role::STAFF);
    }

    /**
     * Alias for getting Parent Group Type IDs
     *
     * @return array
     */
    public function getParentGroupTypeIds()
    {
        return $this->getGroupTypeIds(Role::PARENT);
    }

    /**
     * Alias for getting Student Group Type IDs
     *
     * @return array
     */
    public function getStudentGroupTypeIds()
    {
        return $this->getGroupTypeIds(Role::STUDENT);
    }

    /**
     * Alias for getting Guest Group Type IDs
     *
     * @return array
     */
    public function getGuestGroupTypeIds()
    {
        return $this->getGroupTypeIds(Role::GUEST);
    }

    function getAllByType($type)
    {
        $query = "SELECT name, id, userAccess.type accessType
				  FROM (folder, userGroups )
				  LEFT JOIN userAccess ON cid = sid AND service = " . UserAccess::SERVICE_HOMEPAGE . " AND uid = '" . $_SESSION['id'] . "'
				  WHERE cid = id
				  AND folder.deleted_at IS NULL
				  AND userGroups.type = '$type'
				  ORDER BY name";
        return $this->db->getAll($query);
    }

    function getAllUserAccess($uid)
    {
        $query = "SELECT service, lu_service.name, sid, type FROM userAccess JOIN lu_service ON service=lu_service.id WHERE uid='$uid'";
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
            FROM folder, userGroups
            WHERE cid = id
            AND folder.deleted_at IS NULL
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

    function getOwner($relationID)
    {

        //get the owner(s) of the group
        $query = "SELECT title, firstname, lastname, uid, alt_email, local_email, username
				  FROM userAccess, user
				  WHERE uid = id AND service = " . $this->_service . " AND sid = $relationID AND type = " . UserAccess::ACCESS_FULL;
        return $this->db->getAll($query);
    }


    /**
     * @deprecated replaced by Schoolbox\Permission\Access\RoleAccessMapper::roleMayOwnFolders()
     * @param $userID
     * @return bool
     */
    function allowOwner($userID)
    {
        //checks if the user can become the owner of group
        if (Config::get('group_staff_only')) {
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

        // Do not delete group events anymore.
        // Quoting the disband group dialog:
        // "All members of the group will be removed, but all homepage content will remain."
    }

    function getMemberList($relationID)
    {
        $query = "SELECT title, firstname, lastname, uid, synergy_id, type, group_id, local_email, alt_email, username
				  FROM userAccess, user
				  WHERE uid = id AND service = " . $this->_service . " AND sid = $relationID AND type < " . UserAccess::ACCESS_FULL . " AND type > " . UserAccess::ACCESS_DENY . " AND user.deleted_at IS NULL
				  ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    function getWaitingList($relationID)
    {
        $query = "SELECT title, firstname, lastname, uid, synergy_id
				  FROM userAccess, user
				  WHERE service = " . $this->_service . " AND sid = $relationID AND type = " . UserAccess::ACCESS_DENY . " AND user.id = uid AND user.deleted_at IS NULL
				  ORDER BY lastname, firstname";
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

    /**
     * TODO remove this and update/remove everywhere which uses it:
     * * sync scripts
     * * add users to group
     *
     * @param     $relationID
     * @param     $userID
     * @param int $type
     * @return bool
     */
    function addUser($relationID, $userID, $type = UserAccess::ACCESS_READ)
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
     * TODO remove this and update/remove everywhere which uses it:
     * * group delete
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
        return $this->getPermission($category_id, $user_id) >= UserAccess::ACCESS_READ
        && $this->isParent($user_id)
        && !$this->involvedInGroup($user_id, $category_id);
    }

    /**
     * The new getPermission method, which uses `\Schoolbox\Permission\Access\Access::getFolderAccess`
     * under the hood.
     *
     * This only works when `$this->_service` is `UserAccess::SERVICE_HOMEPAGE` - if it
     * isn't, it proxies through to `$this->legacyGetPermission`.
     *
     * @param  int  $relationID The ID of the object to get permissions on.
     *                          Depending on `$this->_service` could be a
     *                          folder, campus, residential house, or ignored.
     * @param  int  $userID     The user to get permissions for.
     *                          If omitted, defaults to current user.
     * @param  bool $cache      If false, ignore cache.
     * @return int
     */
    function getPermission($relationID, $userID = 0, $cache = true)
    {
        if ($userID == 0) {
            if ($_SESSION['id']) {
                $userID = $_SESSION['id'];
                $this->cacheUser[$userID] = $_SESSION['user'];
            } else {
                return UserAccess::ACCESS_DENY;
            }
        }

        // Cache users as well

        if (!isset($this->cacheUser[$userID]) || !$cache) {
            try {
                $this->cacheUser[$userID] = $user = UserMapper::getInstance()->findById($userID);
            } catch (MapperException $e) {
                return UserAccess::ACCESS_DENY;
            }
        } else {
            $user = $this->cacheUser[$userID];
        }

        if ($this->_service == UserAccess::SERVICE_HOMEPAGE) {
            return Access::getInstance()->getFolderAccess($user, $relationID, $cache);
        } else {
            return $this->legacyGetPermission($relationID, $user);
        }
    }

    /**
     * The old getPermission method, used when `$this->_service` isn't
     * `UserAccess::SERVICE_HOMEPAGE`.
     *
     * @param  int  $relationID The ID of the object to get permissions on.
     *                          Depending on `$this->_service` could be a
     *                          folder, campus, residential house, or ignored.
     * @param  UserModel $user  The user to get permissions for.
     * @param  bool      $cache If false, ignore cache.
     * @return int
     */
    private function legacyGetPermission($relationID, UserModel $user, $cache = true)
    {
        $userID = $user->getId();

        /* Cached results */
        if ($cache && isset($this->cache[$this->_service][$relationID][$userID])) {
            return $this->cache[$this->_service][$relationID][$userID];
        }

        // if superuser just return highest permission type
        if ($user->isSuperuser()) {
            $this->cache[$this->_service][$relationID][$userID] = UserAccess::ACCESS_FULL;
            return UserAccess::ACCESS_FULL;
        }

        $serviceIdFilter = '';
        if (in_array($this->_service, array(UserAccess::SERVICE_CALENDAR, UserAccess::SERVICE_HOUSEMASTER))) {
            $serviceIdFilter = "AND sid = {$this->db->smartQuote($relationID)}";
        }

        // check individual access
        $query = "
            SELECT type
            FROM userAccess
            WHERE uid = {$this->db->smartQuote($userID)}
            $serviceIdFilter
            AND ((service = {$this->db->smartQuote($this->_service)}))
            ORDER BY `type` DESC LIMIT 1
        ";

        $permission = $this->db->getOne($query);
        $permission = (int)$permission;

        // allow staff write access to UserAccess::SERVICE_NEWS by default except at shore...
        if ($user->getRole()->isStaff()
            && (Config::get('news_staff_post_enabled') == '1')
            && ($this->_service == UserAccess::SERVICE_NEWS)
            && ($permission < UserAccess::ACCESS_WRITE)
        ) {
            $permission = UserAccess::ACCESS_WRITE;
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
        return $this->isUserOfType($userID, Role::GUEST);
    }

    /**
     * Find out if the userID belongs to a student
     *
     * @param int $userID
     * @return bool
     */
    public function isStudent($userID = 0)
    {
        return $this->isUserOfType($userID, Role::STUDENT);
    }

    /**
     * Find out if the userID belongs to a parent
     *
     * @param int $userID
     * @return bool
     */
    public function isParent($userID = 0)
    {
        return $this->isUserOfType($userID, Role::PARENT);
    }

    /**
     * Find out if the userID belongs to a staff
     *
     * @param int $userID
     * @return bool
     */
    public function isStaff($userID = 0)
    {
        return $this->isUserOfType($userID, Role::STAFF);
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
        $query = "SELECT userAccess.type FROM userAccess, folder
			WHERE userAccess.service = '" . $this->_service . "'
			AND userAccess.uid = '$uid'
			AND userAccess.sid = folder.id
			AND folder.deleted_at IS NULL
			AND folder.id = '$cid'";
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

        $userMapper = new UserMapper();
        try {
            $user = $userMapper->findById($userID);
        } catch (MapperException $e)  {
            /* Just return an empty set if the user does not exist */
            return $allFolders;
        }

        $folderMapper = new FolderMapper();

        if ($user->getExternalId()) {
            $externalUser = new External_User;
            $externalSubjectList = $externalUser->getSubjectList($user);

            if (is_array($externalSubjectList)) {
                foreach ($externalSubjectList as $currentSubject) {
                    $folder = $folderMapper->findForCampusAndClass($currentSubject['campusCode'] ?? '', $currentSubject['code']);
                    if (!$folder) {
                        continue;
                    }

                    /** @var $folder Schoolbox\Resource\Folder\Model */
                    $template = null;
                    if ($folder->getHomepage() instanceof \Schoolbox\Resource\HomepageConfig) {
                        $template = $folder->getHomepage()->getTemplate()->getId();
                    }

                    $allFolders[$folder->getId()] = array(
                        'id' => $folder->getId(),
                        'name' => $folder->getName(),
                        'parent' => $folder->getParent()->getId(),
                        'deleted_at' => $folder->getDeletedAt(),
                        'code' => $folder->getCodeString(),
                        'template' => $template,
                    );
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
     * @see Schoolbox\Resource\FolderMapper->getForUser() for an implementation
     *      that uses the mapper classes.
     */
    function involvedInCategoryList($userID, $includeSubjectList = true, $accessType = UserAccess::ACCESS_READ, $requireHomepage = false)
    {

        $catList = array();

        if ($includeSubjectList == true && Config::get('external_enable')) {
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
            ? ('AND folder.id NOT IN (' . implode(',', $excluded_id_list) . ')')
            : '';
        $homepageJoin = $requireHomepage ? ' JOIN homepage_config ON folder.id = homepage_config.cid ' : '';

        $query = "
			SELECT
			folder.name, folder.id, type, 
			GROUP_CONCAT(cc.code) as code
			FROM userAccess, folder LEFT JOIN folder_code cc ON folder.id = cc.folder_id AND cc.deleted_at IS NULL
			$homepageJoin
			WHERE userAccess.uid = '$userID'
			AND userAccess.sid = folder.id
			AND userAccess.service = '" . $this->_service . "'
			AND userAccess.type >= '$accessType'
			AND folder.deleted_at IS NULL
			$exclude_part
			GROUP BY folder.id
			ORDER BY folder.name
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
    function involvedCategoryList($id, $subjects = '', $accessType = UserAccess::ACCESS_READ)
    {
        //returns a list of categories that the user is involved in
        //optional accessType specifies read=1 , write=2, ownership=3 access levels

        if (is_array($subjects)) {
            foreach ($subjects as $subject) {
                //convert code to id and get name
                $query = "SELECT name, id, cc.code FROM folder JOIN folder_code cc ON id = cc.folder_id WHERE cc.code = '" . $subject['code'] . "'";
                if (is_array($catTmp = $this->db->getRow($query))) {
                    $catList[] = $catTmp;
                }
            }
        }

        //get list of groups involved in
        $query = "SELECT folder.name, folder.id, type, GROUP_CONCAT(cc.code) as code
        FROM userAccess, folder
        LEFT JOIN folder_code cc ON folder.id = cc.folder_id AND cc.deleted_at IS NULL
		WHERE userAccess.uid = '$id' AND userAccess.sid = folder.id AND userAccess.service = '" . $this->_service . "'
		AND userAccess.type >= '$accessType'
		AND folder.deleted_at IS NULL
		GROUP BY folder.id
		ORDER BY folder.name";

        $this->db->query($query);
        while ($result = $this->db->getNextArray()) {
            $catList[] = $result;
        }

        return $catList;
    }


    public function getGroupName($groupID)
    {
        $query = "SELECT name FROM groups WHERE id = \"{$groupID}\"";
        return $this->db->getOne($query);
    }
}
