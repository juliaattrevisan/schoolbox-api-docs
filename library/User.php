<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Role;
use Schoolbox\User\Formatter;

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
            // TODO SA-8354: audit all places this could be used: replace with mapper
            $this->userNotifier = new Schoolbox\UserNotifier(new Schoolbox\Config\Loader());
        }
        return $this->userNotifier;
    }

    public function getByID($id)
    {
        $query = "SELECT user.id as uid, title, firstname, preferred_name, lastname, username, `groups`.name AS gname, group_id, lu_year_level.name AS year, `key`, hash, eportfolio_enabled,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, webmail, userCampus.campus, luCampus.name as campusName,
                        IF(superusers.id, 1, 0) as su, house, residential_house_id as residential_house, mail_handler, enabled
                FROM user
                    LEFT JOIN `groups` ON user.group_id = `groups`.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
                    LEFT JOIN superusers ON user.id = superusers.id
                    LEFT JOIN lu_year_level ON user.year_id = lu_year_level.id
                WHERE user.id = '" . $this->db->quote($id) . "'
                AND user.deleted_at IS NULL";
        return $this->db->getRow($query);
    }

    public function getByUsername($username)
    {
        $query = "SELECT user.id as uid, title, firstname, preferred_name, lastname, username, `groups`.name AS gname, group_id, lyl.name AS year, eportfolio_enabled,`key`, hash,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, webmail, userCampus.campus, IF(superusers.id, 1, 0) as su, house, mail_handler, calendar_view, enabled
                FROM user
                    LEFT JOIN `groups` ON user.group_id = `groups`.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN superusers ON user.id = superusers.id
                    LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id
                WHERE user.username = " . $this->db->smartQuote($username) . "
                AND user.deleted_at IS NULL";
        return $this->db->getRow($query);
    }

    public function getSuperusers()
    {
        $query = "SELECT user.id uid, title, firstname, preferred_name, lastname, username, local_email, alt_email
                FROM superusers JOIN user ON superusers.id = user.id
                WHERE user.deleted_at IS NULL";
        return $this->db->getAll($query);
    }

    public function getUserCampus($id)
    {
        $campusList  = array();
        $campusquery = "SELECT campus FROM userCampus WHERE uid = '$id'";
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
        $query = "SELECT user.id AS uid, title, firstname, lastname, username, `groups`.name AS gname, group_id,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, userCampus.campus, lyl.name, eportfolio_enabled, house, enabled
                FROM user
                    LEFT JOIN `groups` ON user.group_id = `groups`.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id
                WHERE user.synergy_id = '$sid'
                AND user.deleted_at IS NULL";
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
    protected function getSearchFilter(\Schoolbox\User $user): string
    {
        $excludeRoles = [];
        /* Staff members shouldn't have all users in search results
         *
         * Anyone other than staff (ie. parents and students) are subjected
         * config options that restrict the search results
         *
         * Guests have special filters that prevent them from getting search
         * results for parents and students
         */
        if ($user->getRole()->isGuest()) {
            $excludeRoles[] = Role::PARENT;
            $excludeRoles[] = Role::STUDENT;
        } elseif (!$user->getRole()->isStaff()) {
            /* Include parent users in search results if the config is enabled
             */
            if (Config::get('parent_search_enable') != 1) {
                $excludeRoles[] = Role::PARENT;
            }
            /* Include students users in search results if the config is enabled
             */
            if (Config::get('student_search_enable') != 1) {
                $excludeRoles[] = Role::STUDENT;
            }
        }

        if (empty($excludeRoles)) {
            return '';
        }

        return " `groups`.`group_type` NOT IN ('" . implode("','", $excludeRoles) . "')";
    }

    /**
     * Search the user table for particular user(s)
     *
     * @param string $keyword
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
                OR CONCAT_WS(' ', title, preferred_name, lastname) LIKE '%1\$s'
                OR CONCAT_WS(' ', preferred_name, lastname) LIKE '%1\$s'
                OR CONCAT_WS(' ', title, lastname) LIKE '%1\$s'
                OR firstname LIKE '%1\$s'
                OR preferred_name LIKE '%1\$s'
                OR lastname LIKE '%1\$s'
                OR username LIKE '%1\$s' ",
                $this->db->quote('%' . $this->db->escapeForLike($keyword) . '%')
            );
        }
        $wherePart .= ') AND user.deleted_at IS NULL';

        //If parent search policy is disabled, only staff will see parents in search results
        //Guests never see parents
        if (($searchFilter = $this->getSearchFilter($_SESSION['user']))) {
            $wherePart .= ' AND ' . $searchFilter;
        }

        /* Show disabled users only if ticked (hide when unticked)
         */
        if (!ConfigLoader::getInstance()->get('show_disabled_users')) {
            $wherePart .= ' AND `user`.enabled = 1';
        }

        // retrieve results
        /* Allow search on synergy id
         */
        $query = "
            SELECT user.*, `groups`.name AS group_name, lu_year_level.name AS year_name
            FROM `user`
            LEFT JOIN `groups` ON `user`.group_id = `groups`.id
            LEFT JOIN `lu_year_level` ON `user`.year_id = `lu_year_level`.id AND `lu_year_level`.deleted_at IS NULL
            WHERE $wherePart
        ";
        $query .= " ORDER BY lastname, chosen_name ASC
                        LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*) FROM `user` LEFT JOIN `groups` ON `user`.group_id = `groups`.id WHERE $wherePart";

        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }


    /**
     * Search for exact matches, e.g. "zane" rather than LIKE
     *
     * @param string $keyword
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
            $wherePart .= ' AND ' . $searchFilter;
        }

        /* Show disabled users only if ticked (hide when unticked)
         */
        if (!ConfigLoader::getInstance()->get('show_disabled_users')) {
            $wherePart .= ' AND `user`.enabled = 1';
        }

        // retrieve results
        $query = "SELECT user.*, `groups`.name AS group_name
                    FROM `user`
                    LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                    WHERE (
                        firstname = '$keyword'
                        OR preferred_name = '$keyword'
                        OR lastname = '$keyword'
                        OR username = '$keyword'
                    )
                    $wherePart
                    ORDER BY lastname, chosen_name ASC
                    LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*)
                                FROM `user`
                                LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                                WHERE (
                                    firstname = '$keyword'
                                    OR preferred_name = '$keyword'
                                    OR lastname = '$keyword'
                                    OR username = '$keyword'
                                )
                                $wherePart";
        $count       = $this->db->getOne($count_query);
        return $this->db->getAll($query);
    }

    /**
     * Find a user based on a keyword, for an AJAX response.
     *
     * @param string    $keyword
     * @param int|array $group
     * @param array     $residentialHouseIds
     * @param int|bool  $limit
     * @return array|bool
     * @throws Exception
     */
    public function ajaxSearch($keyword, $group = 0, $residentialHouseIds = array(), $limit = false)
    {
        //search for a user based on at least one of the above fields
        //assumes at least one contains something
        $query = '
            SELECT
                user.id AS uid,
                title,
                firstname,
                preferred_name,
                lastname,
                username,
                `groups`.name AS gname,
                `groups`.group_type as gtype,
                `groups`.id AS gid,
                lyl.name AS year,
                local_email,
                alt_email,
                synergy_id
            FROM user
            LEFT JOIN `groups` ON user.group_id = `groups`.id
            LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id
            WHERE user.deleted_at IS NULL
            AND
        ';

        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $param = $this->db->quote($keyword);
                $query .= "( synergy_id = $param  ) ";
            } else {
                // TODO account for preferred name:
                //      alternately remove and replace with API search
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $firstName = $this->db->escapeForLike($name[1]);
                    $lastName  = $this->db->escapeForLike($name[2]);
                    $query .= "
                        ( firstname like '%{$firstName}%' and lastname like '%{$lastName}%' )
                        or
                        ( preferred_name like '%{$firstName}%' and lastname like '%{$lastName}%' )
                    ";
                } else {
                    $param = $this->db->escapeForLike($keyword);
                    $query .= "(
                        firstname like '%$param%'
                        or preferred_name like '%$param%'
                        or lastname like '%$param%'
                        or username like '%$param%'
                    ) ";
                }
            }
            if (is_array($group)) {
                // If group = [0]: return all matching users regardless of role
                $group = array_filter($group);
                if (!empty($group)) {
                    $query .= "AND group_id IN (" . implode(',', array_map(array($this->db, 'smartQuote'), $group)) . ") ";
                }
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
            $query .= ' AND ' . $searchFilter;
        }
        $query .= "ORDER BY group_id, lyl.sequence, lastname, chosen_name";
        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }
        return $this->db->getAll($query);
    }

    public function getCampusList()
    {
        $query = "SELECT * FROM luCampus WHERE luCampus.deleted_at IS NULL ORDER BY sequence";
        return $this->db->getAll($query);
    }

    public function getGroupList()
    {
        $query = "SELECT * FROM `groups`
                  WHERE deleted_at IS NULL
                  ORDER BY name";
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
     * Gets all in the system who are in a particular groupID
     *
     * @param int|array $groupID
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
     * @param array  $userInfo
     * @param string $format
     *
     * @return mixed|null|string
     */
    public function formatName($userInfo, $format = null)
    {
        $formatter = empty($format)
            ? (new Formatter\Factory())->buildFromNothing()
            : new Formatter\Formatter($format);
        return $formatter->formatArray($userInfo);
    }
}
