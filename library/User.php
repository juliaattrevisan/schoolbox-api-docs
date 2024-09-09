<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Predicate\Builder;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Role;
use Schoolbox\User\Formatter;

class User extends DeprecatedModel
{
    public function __construct()
    {
        $this->_table = "user";
        $this->_index = "id";
        parent::__construct();
    }


    public function getByID($id)
    {
        $query = "
            SELECT
                user.id as uid, title, firstname, preferred_name, lastname, username, `groups`.name AS gname, group_id, lu_year_level.name AS year, `key`, hash, eportfolio_enabled,
                position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, userCampus.campus, luCampus.name as campusName,
                IF(superusers.id, 1, 0) as su, house, residential_house_id as residential_house, mail_handler, enabled
            FROM user
            LEFT JOIN `groups` ON user.group_id = `groups`.id
            LEFT JOIN userCampus ON user.id = userCampus.uid
            LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id
            LEFT JOIN superusers ON user.id = superusers.id
            LEFT JOIN lu_year_level ON user.year_id = lu_year_level.id
            WHERE user.id = ?
            AND user.deleted_at IS NULL
        ";
        return $this->db->query($query, [$id])->fetch();
    }

    public function getByUsername($username)
    {
        $query = "SELECT user.id as uid, title, firstname, preferred_name, lastname, username, `groups`.name AS gname, group_id, lyl.name AS year, eportfolio_enabled,`key`, hash,
                        position_title, hide_timetable, hide_contacts, synergy_id, local_email, alt_email, userCampus.campus, IF(superusers.id, 1, 0) as su, house, mail_handler, calendar_view, enabled
                FROM user
                    LEFT JOIN `groups` ON user.group_id = `groups`.id
                    LEFT JOIN userCampus ON user.id = userCampus.uid
                    LEFT JOIN superusers ON user.id = superusers.id
                    LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id
                WHERE user.username = ?
                AND user.deleted_at IS NULL";
        return $this->db->query($query, [$username])->fetch();
    }

    public function getSuperusers()
    {
        $query = "SELECT user.id uid, title, firstname, preferred_name, lastname, username, local_email, alt_email
                FROM superusers JOIN user ON superusers.id = user.id
                WHERE user.deleted_at IS NULL";
        return $this->db->query($query)->fetchAll();
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
        return $this->db->query($query)->fetch();
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
        $wherePart = "(synergy_id = :keyword";
        $params    = ['keyword' => $keyword];
        if (mb_strlen($keyword, 'UTF-8') >= 2) {
            $wherePart .= " OR CONCAT_WS(' ', title, firstname, lastname) LIKE :keywordLike
                OR CONCAT_WS(' ', firstname, lastname) LIKE :keywordLike
                OR CONCAT_WS(' ', title, preferred_name, lastname) LIKE :keywordLike
                OR CONCAT_WS(' ', preferred_name, lastname) LIKE :keywordLike
                OR CONCAT_WS(' ', title, lastname) LIKE :keywordLike
                OR firstname LIKE :keywordLike
                OR preferred_name LIKE :keywordLike
                OR lastname LIKE :keywordLike
                OR username LIKE :keywordLike"
            ;
            $params['keywordLike'] = '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%';
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

        $count       = $this->db->query($count_query, $params)->fetchColumn();
        return $this->db->query($query, $params)->fetchAll();
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
                        firstname = :keyword
                        OR preferred_name = :keyword
                        OR lastname = :keyword
                        OR username = :keyword
                    )
                    $wherePart
                    ORDER BY lastname, chosen_name ASC
                    LIMIT $offset, $limit;";
        // retreive count
        $count_query = "SELECT COUNT(*)
                                FROM `user`
                                LEFT JOIN `groups` ON `user`.group_id = `groups`.id
                                WHERE (
                                    firstname = :keyword
                                    OR preferred_name = :keyword
                                    OR lastname = :keyword
                                    OR username = :keyword
                                )
                                $wherePart";
        $count       = $this->db->query($count_query, ['keyword' => $keyword])->fetchColumn();
        return $this->db->query($query, ['keyword' => $keyword])->fetchAll();
    }

    /**
     * Find a user based on a keyword, for an AJAX response.
     *
     * @param string    $keyword
     * @param int|array $group
     * @param array     $residentialHouseIds
     * @param int|bool  $limit
     * @param bool|null $enabled
     * @return array|bool
     * @throws Exception
     */
    public function ajaxSearch($keyword, $group = 0, $residentialHouseIds = array(), $limit = false, bool $enabled = null)
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

        $params = [];
        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $query .= "( synergy_id = :externalId ) ";
                $params['externalId'] = $keyword;
            } else {
                // TODO account for preferred name:
                //      alternately remove and replace with API search
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $query .= "(
                        ( firstname like :firstname and lastname like :lastname )
                        or
                        ( preferred_name like :firstname and lastname like :lastname )
                    ) or ";
                    $params['firstname'] = '%' . $this->db->getQuoter()->escapeForLike($name[1]) . '%';
                    $params['lastname'] = '%' . $this->db->getQuoter()->escapeForLike($name[2]) . '%';
                }
                $query .= "(
                    firstname like :name
                    or preferred_name like :name
                    or lastname like :name
                    or username like :name
                ) ";
                $params['name'] = '%' . $this->db->getQuoter()->escapeForLike($keyword) . '%';
            }
            if (is_array($group)) {
                // If group = [0]: return all matching users regardless of role
                $group = array_filter($group);
                if (!empty($group)) {
                    $query .= "AND group_id IN " . Builder::getArrayPlaceholders($group, 'group');
                    $params += Builder::getArrayParameters($group, 'group');
                }
            } else {
                if (is_numeric($group) && $group > 0) {
                    $query .= "AND group_id = :groupId ";
                    $params['groupId'] = $group;
                }
            }
        } else {
            if ($group) {
                $query .= "group_id = :groupId ";
                $params['groupId'] = $group;
            } else {
                error(2);
            }
        }
        // Only students in boarding house are fetched
        if (!empty($residentialHouseIds)) {
            $query .= "AND residential_house_id IN " . Builder::getArrayPlaceholders($residentialHouseIds, 'reshouse');
            $params += Builder::getArrayParameters($residentialHouseIds, 'reshouse');
        }
        if (($searchFilter = $this->getSearchFilter($_SESSION['user']))) {
            $query .= ' AND ' . $searchFilter;
        }
        // if limiting to those who are enabled or not
        if (null !== $enabled) {
            $query .= sprintf(" AND enabled = %s ", $enabled ? 1 : 0);
        }
        $query .= "ORDER BY group_id, lyl.sequence, lastname, chosen_name";
        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }
        return $this->db->query($query, $params)->fetchAll();
    }

    public function getCampusList()
    {
        $query = "SELECT * FROM luCampus WHERE luCampus.deleted_at IS NULL ORDER BY sequence";
        return $this->db->query($query)->fetchAll();
    }

    public function getGroupList()
    {
        $query = "SELECT * FROM `groups`
                  WHERE deleted_at IS NULL
                  ORDER BY name";
        return $this->db->query($query)->fetchAll();
    }

    /**
     * @param string $username
     * @param int $limit
     * @return array|bool
     */
    public function getUserLogins($username, int $limit = 10): array|bool
    {
        $query = "SELECT date, ip, login FROM login_log WHERE username = ? ORDER BY date DESC LIMIT 0,$limit";
        return $this->db->query($query, [$username])->fetchAll();
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
