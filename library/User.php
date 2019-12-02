<?php

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccess;


class User extends DeprecatedModel
{
    /**
     * A copy of the user notifier - it is called when a user changes.
     *
     * @var Schoolbox\UserNotifier
     */
    protected $userNotifier;

    public function __construct()
    {
        $this->_table = "user";
        $this->_index = "id";
        parent::__construct();
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
     * Gets the ID associated with a particular campus name. Returns false if
     * the campus name was not matched.
     *
     * @param  string $name
     *
     * @return int
     */
    public function getCampusID($name)
    {
        $query = "SELECT id FROM luCampus WHERE name LIKE '$name'";
        return $this->db->getOne($query);
    }

    public function getGroupID($name)
    {
        $query = "SELECT id FROM groups WHERE name LIKE '$name'";
        return $this->db->getOne($query);
    }

    public function getAllInCategory($categoryID)
    {
        $query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email, synergy_id
                  FROM user, userAccess
                  WHERE id = uid AND service = '" . UserAccess::SERVICE_HOMEPAGE . "' AND sid = '" . $categoryID . "'
                  AND type > 0
                  AND user.deleted_at IS NULL
                  ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    public function getManagers($categoryID)
    {
        $query = "SELECT title, id as uid, firstname, lastname, type, username, local_email, alt_email, synergy_id 
                  FROM user, userAccess
                  WHERE id = uid AND service = '" . UserAccess::SERVICE_HOMEPAGE . "' AND sid = '" . $categoryID . "' 
                  and type in(3,2)
                  AND user.deleted_at IS NULL
                  ORDER BY lastname, firstname";
        return $this->db->getAll($query);
    }

    public function getByID($id)
    {
        $query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, `key`, hash, eportfolio_enabled,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, webmail, userCampus.campus, luCampus.name as campusName,
                        IF(superusers.id, 1, 0) as su, house, residential_house_id as residential_house, mail_handler, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
                    LEFT JOIN superusers ON user.id = superusers.id
                WHERE user.id = '" . $this->db->quote($id) . "'
                AND user.deleted_at IS NULL
                ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    public function getByUsername($username)
    {
        $query = "SELECT user.id as uid, title, firstname, lastname, username, groups.name AS gname, group_id, year, eportfolio_enabled,`key`, hash,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, webmail, userCampus.campus, IF(superusers.id, 1, 0) as su, house, mail_handler, calendar_view, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN superusers ON user.id = superusers.id
                WHERE user.username = " . $this->db->smartQuote($username) . " 
                AND user.deleted_at IS NULL
                ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    public function getSuperusers()
    {
        $query = "SELECT user.id uid, title, firstname, lastname, username, local_email, alt_email
                FROM superusers JOIN user ON superusers.id = user.id
                WHERE user.deleted_at IS NULL";
        return $this->db->getAll($query);
    }

    public function getUserCampus($id)
    {
        $campusList  = array();
        $campusquery = "SELECT campus FROM userCampus WHERE uid = '$id' ORDER BY defaultCampus DESC";
        $this->db->query($campusquery);
        if ($this->db->countRows() > 0) {
            while ($campus = $this->db->getNextArray()) {
                if ($campus['campus']) {
                    $campusList[] = $campus['campus'];
                }
            }
        } else {
            //Place user in campus 1 by default in not assigned
            $campusList[] = 1;
        }
        return $campusList;
    }

    public function getBySynergy($sid)
    {
        $query = "SELECT user.id AS uid, title, firstname, lastname, username, groups.name AS gname, group_id,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, userCampus.campus, year, eportfolio_enabled, house, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                WHERE user.synergy_id = '$sid' 
                AND user.deleted_at IS NULL
                ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    /**
     * Search for a user by synergy ID, restricted to a group or group array
     *
     * @param $sid
     * @param $gid
     *
     * @return array|bool
     */
    public function getBySynergyAndGroup($sid, $gid)
    {
        $query = "SELECT user.id AS uid, title, firstname, lastname, username, groups.name AS gname, group_id,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, userCampus.campus, year, eportfolio_enabled, house, enabled
                FROM user
                    LEFT JOIN groups ON user.group_id = groups.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                WHERE user.synergy_id = '$sid'
                AND user.deleted_at IS NULL
                AND ";
        if (is_array($gid)) {
            $query .= "group_id IN (" . implode(", ", $gid) . ")";
        } else {
            $query .= "group_id = '$gid'";
        }
        $query .= " ORDER BY userCampus.defaultCampus DESC";
        return $this->db->getRow($query);
    }

    /**
     * Gets the part of a WHERE query that filters out parents from:
     * * Guests
     * * Staff, if config.parent_search_enable is off
     * * Students if config.student_search_enable is off
     *
     * @param  \Schoolbox\User $user
     * @return string
     */
    protected function getSearchFilter($user)
    {
        $searchFilter = '';
        if ($user->getRole()->isGuest() ||
            (!$user->getRole()->isStaff() && Cfg::get('parent_search_enable') != 1)
        ) {
            $searchFilter =  " `groups`.`group_type` != 'parent' AND ";
        }
        if ($user->getRole()->isGuest() ||
            (!$user->getRole()->isStaff() && Cfg::get('student_search_enable') != 1)
        ) {
            $searchFilter .= " `groups`.`group_type` != 'student' AND ";
        }
        return $searchFilter."1=1 ";
    }

    /**
     * Search the user table for particular user(s)
     *
     * @param        $keyword
     * @param int    $offset
     * @param int    $limit
     * @param null   $count
     *
     * @return array|bool
     */
    public function search($keyword, $offset = 0, $limit = 20, &$count = null)
    {
        // test for exact search, and if so return those results instead
        if (preg_match('#".+"#', $keyword)) {
            // strip the prepended and appended quote characters
            $keyword = substr($keyword, 1, strlen($keyword) - 2);
            return $this->searchExact($keyword, $offset, $limit, $count);
        }
        // Don't allow LIKE searches on keywords < 2 chars long
        $wherePart = sprintf("( synergy_id = '%s'", $this->db->quote($keyword));
        if (mb_strlen($keyword, 'UTF-8') >= 2) {
            $wherePart .= sprintf(
                " OR CONCAT_WS(' ', title, firstname, lastname) LIKE '%1\$s'
                OR CONCAT_WS(' ', firstname, lastname) LIKE '%1\$s'
                OR CONCAT_WS(' ', title, lastname) LIKE '%1\$s'
                OR firstname LIKE '%1\$s'
                OR lastname LIKE '%1\$s'
                OR username LIKE '%1\$s' ",
                $this->db->quote('%' . $this->db->escapeForLike($keyword) . '%')
            );
        }
        $wherePart .= ') AND user.deleted_at IS NULL';

        //If parent search policy is disabled, only staff will see parents in search results
        //Guests never see parents
        if (($searchFilter = $this->getSearchFilter($_SESSION['user']))) {
            $wherePart .= ' AND '.$searchFilter;
        }

        /* Show disabled users only if ticked (hide when unticked)
         */
        if (!ConfigMapper::getInstance()->get('show_disabled_users')) {
            $wherePart .= ' AND `user`.enabled = 1';
        }

        // retrieve results
        /* Allow search on synergy id
         */
        $query = "
            SELECT user.*, groups.name AS group_name, luCampus.name AS campus_name
            FROM `user`
            LEFT JOIN `groups` ON `user`.group_id = `groups`.id
            LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
            LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
            WHERE $wherePart
        ";
        $query .= " ORDER BY lastname, firstname ASC
                        LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*) FROM `user` LEFT JOIN `groups` ON `user`.group_id = `groups`.id WHERE $wherePart";

        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }


    /**
     * Search for exact matches, e.g. "zane" rather than LIKE
     *
     * @param        $keyword
     * @param int    $offset
     * @param int    $limit
     * @param null   $count
     * @return array|bool
     */
    public function searchExact(
        $keyword,
        $offset = 0,
        $limit = 20,
        &$count = null
    ) {
        $keyword = $this->db->quote($keyword);

        //If parent search policy is disabled, only staff will see parents in search results
        //Guests never see parents
        $wherePart = ' AND user.deleted_at IS NULL';
        if (($searchFilter = $this->getSearchFilter($_SESSION['user']))) {
            $wherePart .= ' AND '.$searchFilter;
        }

        /* Show disabled users only if ticked (hide when unticked)
         */
        if (!ConfigMapper::getInstance()->get('show_disabled_users')) {
            $wherePart .= ' AND `user`.enabled = 1';
        }

        // retrieve results
        $query = "SELECT user.*, groups.name AS group_name, luCampus.name AS campus_name
                    FROM `user`
                    LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                    LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
                    LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
                    WHERE ( firstname = '$keyword'
                    OR lastname = '$keyword'
                    OR username = '$keyword' )
                    $wherePart
                    ORDER BY lastname, firstname ASC
                    LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*)
                                FROM `user`
                                LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                                WHERE ( firstname = '$keyword'
                                OR lastname = '$keyword'
                                OR username = '$keyword' )
                                $wherePart";
        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }

    public function ajaxSearch($keyword, $group = 0, $residentialHouseIds = array(), $limit = false)
    {
        //search for a user based on at least one of the above fields
        //assumes at least one contains something
        $query = "
            SELECT
                user.id AS uid,
                title,
                firstname,
                lastname,
                username,
                groups.name AS gname,
                year,
                local_email,
                alt_email,
                synergy_id,
                luCampus.id as campusId,
                luCampus.name as campusName
            FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN userCampus defaultCampus
              ON user.id = defaultCampus.uid
              AND defaultCampus.defaultCampus IS TRUE
            LEFT JOIN luCampus
              ON defaultCampus.campus = luCampus.id
            WHERE user.deleted_at IS NULL
            AND
        ";
        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $param = $this->db->quote($keyword);
                $query .= "( synergy_id = $param OR year = $param ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $firstName = $this->db->escapeForLike($name[1]);
                    $lastName  = $this->db->escapeForLike($name[2]);
                    $query .= "( firstname like '%{$firstName}%' and lastname like '%{$lastName}%' ) ";
                } else {
                    $param = $this->db->escapeForLike($keyword);
                    $query .= "( firstname like '%$param%' or lastname like '%$param%' or username like '%$param%' ) ";
                }
            }
            if (is_array($group)) {
                $query .= "AND group_id IN (" . implode(',', array_map(array($this->db, 'quote'), $group)) . ") ";
            } else {
                if (is_numeric($group) && $group > 0) {
                    $param = $this->db->quote($group);
                    $query .= "AND group_id = '$param' ";
                }
            }
        } else {
            if ($group) {
                $param = $this->db->quote($group);
                $query .= "group_id = '$param' ";
            } else {
                error(2);
            }
        }
        // Only students in boarding house are fetched
        if (!empty($residentialHouseIds)) {
            $query .= "AND residential_house_id IN ("
                . implode(',', array_map('intval', $residentialHouseIds))
                . ')';
        }
        if (($searchFilter = $this->getSearchFilter($_SESSION['user']))) {
            $query .= ' AND '.$searchFilter;
        }
        $query .= "ORDER BY group_id, year, lastname, firstname";
        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }
        return $this->db->getAll($query);
    }

    /**
     * Sets the user's password in the database.
     *
     * @param int    $userID
     * @param string $newPassword
     *
     * @return bool
     */
    public function setPasswordInternal($userID, $newPassword)
    {
        // Babushka doll - a mapper in a handler!
        // This is going to be slow as hell but the only place user handler
        // should still save users is in the sync scripts, so who cares.
        // Also, most syncs don't even save passwords.
        $userMapper = \Schoolbox\UserMapper::getInstance();
        try {
            $user            = $userMapper->findById($userID);
            $passwordService = new \Alaress\Password\Service();
            $user
                ->setRetrievablePassword($newPassword)
                ->setPassword($passwordService->hash($newPassword));
            $userMapper->save($user);
            return 1;
        } catch (\Schoolbox\Mapper\Exception $e) {
            return 0;
        }
    }

    public function getCampusList()
    {
        $query = "SELECT * FROM luCampus";
        return $this->db->getAll($query);
    }

    public function getGroupList()
    {
        $query = "SELECT * FROM groups
                  WHERE deleted_at IS NULL
                  ORDER BY name";
        return $this->db->getAll($query);
    }

    public function getTitleList()
    {
        $query = "SELECT * FROM luTitle";
        return $this->db->getAll($query);
    }

    public function getUserActivity($uid, $limit = 10)
    {
        $query = "SELECT object, action, time, c.name AS folder, f.description AS file, l.description AS link
                    FROM cmsLog
                    LEFT JOIN folder c ON c.id = objectId
                    LEFT JOIN file f ON f.id = objectId
                    LEFT JOIN link l ON l.id = objectId
                    WHERE user = $uid
                    ORDER BY time DESC
                    LIMIT 0,$limit";
        return $this->db->getAll($query);
    }

    public function getUserLogins($username, $limit = 10)
    {
        $query = "SELECT date, ip, login FROM login_log WHERE username like "
            . $this->db->smartQuote($username)
            . " ORDER BY date DESC LIMIT 0,$limit";
        return $this->db->getAll($query);
    }

    /**
     * Deletes this user.
     *
     * As of 16.0 it no longer deletes all traces - users are now soft deleted.
     *
     * @param  int $id
     * @param  int $trans
     * @return bool
     */
    public function delete($id, $trans = 0)
    {
        trigger_error('User::delete() is deprecated - use UserMapper instead', E_USER_DEPRECATED);
        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user       = $userMapper->findById($id);
        $result     = $userMapper->delete($user);
        if ($trans) {
            $userMapper->transferOwnership($id, $trans);
        }
        return $result;
    }

    /**
     * Update an existing user's details
     *
     * @param array $user
     * @return bool
     */
    public function update($user)
    {
        /* Check if the username already exists before updating. If it does
              * exist already, prevent existence of duplicate usernames
              */
        if ($dupeUser = $this->getByUsername($user['username'])) {
            if ($dupeUser['uid'] != $user['id']) {
                return false;
            }
        }
        $campusArray   = $user['campus'];
        $defaultCampus = $user['defaultCampus'];
        unset($user['campus']);
        unset($user['defaultCampus']);
        /* Check if $user['su'] exists and not equal to null
              */
        if (isset($user['su'])) {
            $this->db->delete('superusers', 'id', $user['id']);
            if ($user['su']) {
                $this->db->insert('superusers', array('id' => $user['id']));
            }
            unset($user['su']);
        } /* Check if $user['su'] exists and is null
         */
        else {
            if (array_key_exists('su', $user)) {
                unset($user['su']);
            }
        }
        if (isset($user['password'])) {
            $this->setPasswordInternal($user['id'], $user['password']);
            unset($user['password']);
        }
        parent::update($user);
        if (is_array($campusArray)) {
            $this->db->delete('userCampus', 'uid', $user['id']);
            foreach ($campusArray as $campus) {
                $insertCampus = array(
                    'uid'           => $user['id'],
                    'campus'        => $campus,
                    'defaultCampus' => $campus == $defaultCampus ? 1 : 0
                );
                $this->db->insert('userCampus', $insertCampus);
            }
        } else {
            if (is_numeric($campusArray) && $campusArray > 0) {
                /* Note that this can insert multiple campus if the update only specified a single value like in a bulk load */
                $campusquery = "INSERT IGNORE INTO userCampus (uid, campus) VALUES ('{$user['id']}', '$campusArray')";
                $this->db->query($campusquery);
            }
        }
        // Filthily notify the binder that user was updated
        if(isset($_SESSION) && isset($_SESSION['user'])) {
            $this->getUserNotifier()->manualNotifyUpdated($user['id'], $_SESSION['user']);
        }

        return true;
    }

    /**
     * Create a new user record
     *
     * @param array $user
     *
     * @return int|bool ID of new user, or false if failed
     */
    public function create($user)
    {
        /* Check if the username already exists
              */
        if (is_array($this->getByUsername($user['username']))) {
            return false;
        }
        $campusArray   = $user['campus'];
        $defaultCampus = $user['defaultCampus'];
        $su            = $user['su'];
        $password      = $user['password'];
        unset($user['campus']);
        unset($user['defaultCampus']);
        unset($user['su']);
        unset($user['password']);
        $user['key'] = substr(md5(uniqid() . time()), 0, 25);
        if (is_array($campusArray) && count($campusArray)) {
            $id = parent::create($user);
            if ($password) {
                $this->setPasswordInternal($id, $password);
            }
            if (is_array($campusArray) && $id) {
                foreach ($campusArray as $campus) {
                    $insertCampus = array(
                        'uid'           => $id,
                        'campus'        => $campus,
                        'defaultCampus' => $campus == $defaultCampus ? 1 : 0
                    );
                    $this->db->insert('userCampus', $insertCampus);
                }
            } else {
                if ($id && $campusArray) {
                    $this->db->insert('userCampus', [
                        'uid'           => $id,
                        'campus'        => $campusArray,
                        'defaultCampus' => $campusArray == $defaultCampus ? 1 : 0,
                    ]);
                }
            }
            /* If ticked, add the new user into the Superusers table as well.
                      */
            if ($su) {
                $this->db->insert('superusers', array('id' => $id));
            }
            // Filthily notify the binder that there's a new user
            if(isset($_SESSION) && isset($_SESSION['user'])) {
                $this->getUserNotifier()->manualNotifyCreated($id, $_SESSION['user']);
            }

            return $id;
        }
        return false;
    }


    /**
     * Gets all in the system who are in a particular groupID, restricted by index and limit
     *
     * @param      $groupID
     * @param bool $hasSynergy
     * @param int  $index
     * @param int  $limit
     * @return array
     */
    public function getSelectionByGroup($groupID, $hasSynergy = false, $index = 0, $limit = 0)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE group_id";
        if (is_array($groupID)) {
            $query .= " IN " . '(' . implode(',', $groupID) . ')';
        } else {
            $query .= " = '$groupID'";
        }
        /* Optionally exclude those without synergy ids
              */
        if ($hasSynergy) {
            $query .= " AND synergy_id IS NOT NULL
                        AND synergy_id > 0 ";
        }
        $query .= " 
            AND deleted_at IS NULL 
            ORDER BY lastname ASC, firstname ASC";
        if ($limit) {
            $query .= " LIMIT $index, $limit";
        }
        return $this->db->getAll($query);
    }

    /**
     * Gets all in the system who are in a particular groupID
     *
     * @param int $groupID
     * @param bool $hasSynergy If true (default: false), only returns users with synergy IDs
     * @return array
     */
    public function getByGroup($groupID, $hasSynergy = false)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE group_id";
        if (is_array($groupID)) {
            $query .= " IN " . '(' . implode(',', $groupID) . ')';
        } else {
            $query .= " = '$groupID'";
        }
        /* Optionally exclude those without synergy ids
              */
        if ($hasSynergy) {
            $query .= " AND synergy_id IS NOT NULL
                        AND synergy_id > 0 
                        AND user.deleted_at IS NULL";
        }
        return $this->db->getAll($query);
    }

    /**
     * Format a name from a user object
     *
     * @param  array $userInfo
     * @param string $format
     * @param bool   $useNonBreakingSpace
     *
     * @return mixed|null|string
     */
    public function formatName($userInfo, $format = null, $useNonBreakingSpace = false)
    {
        // Check for custom format
        if (empty($format)) {
            $format = Cfg::get('name_format');
        }
        /* Replace tags */
        preg_match_all('|{\w+}|', $format, $tags);
        if (empty($tags) || empty($userInfo)) {
            return 'Unknown User';
        }
        foreach ($tags[0] as $tag) {
            $key = trim($tag, '{}');
            if (is_string($userInfo[$key])) {
                $value = $userInfo[$key];
            } else {
                $value = '';
            }
            $format = str_replace($tag, $value, $format);
        }
        if ($format = trim($format)) {
            if ($useNonBreakingSpace) {
                $format = preg_replace("#[\s]#", '&nbsp;', $format);
            }
            return $format;
        } elseif ($useNonBreakingSpace) {
            return 'Unknown&nbsp;User';
        } else {
            return 'Unknown User';
        }
    }

    /**
     * Logs user-related activity in the database.
     *
     * @param string $user    The user's username.
     * @param string $event   A simple code to describe the error, 20 chars max.
     * @param string $level   The log level: trace, debug, info, warning, error
     * @param string $message Optional extra information, 250 chars max.
     *
     * @return bool
     */
    public function logUserPasswordChange($user, $event, $level, $message = '')
    {
        //
        // I will NOT log passwords here!
        // when user changes password (change_pwd, info)
        // when user tries to change password but enters wrong password (change_pwd_fail, warn)
        // when user tries but is foild by Synergetic (change_pwd_syn_fail, warn)
        // when an admin or someone changes other user's password (change_other_password, info)
        return $this->db->insert(
            'user_activity_log',
            array(
                'username' => $user,
                'date'     => 'NOW()',
                'ip'       => $_SERVER['REMOTE_ADDR'],
                'event'    => $event,
                'level'    => $level,
                'message'  => $message,
            )
        );
    }
}
