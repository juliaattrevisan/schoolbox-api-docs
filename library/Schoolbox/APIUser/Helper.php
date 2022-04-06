<?php

namespace Schoolbox\APIUser;

use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

class Helper
{

    protected UserMapper $userMapper;

    /**
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Find a user based on a keyword, for an AJAX response.
     * stripped and updated from `library/User.php`
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
                lastname,
                username,
                groups.name AS gname,
                groups.id AS gid,
                lyl.name AS year,
                local_email,
                alt_email,
                synergy_id
            FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id
            WHERE user.deleted_at IS NULL
            AND
        ';

        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $param = $this->userMapper->getDatabase()->quote($keyword);
                $query .= "( synergy_id = $param  ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
//                    $firstName = $this->userMapper->getDatabase()->quote($name[1]);
//                    $lastName  = $this->userMapper->getDatabase()->quote($name[2]);
//                    $query .= "( firstname like '%{$firstName}%' and lastname like '%{$lastName}%' ) ";
                    $query .= "( firstname like '%{$name[1]}%' and lastname like '%{$name[2]}%' ) ";
                } else {
//                    $param = $this->userMapper->getDatabase()->quote($keyword);
                    $query .= "( firstname like '%$keyword%' or lastname like '%$keyword%' or username like '%$keyword%' ) ";
                }
            }
            if (is_array($group)) {
                // If group = [0]: return all matching users regardless of role
                $group = array_filter($group);
                if (!empty($group)) {
                    $query .= "AND group_id IN (" . implode(',', array_map(array($this->userMapper->getDatabase(), 'smartQuote'), $group)) . ") ";
                }
            } else {
                if (is_numeric($group) && $group > 0) {
                    $param = $this->userMapper->getDatabase()->quote($group);
                    $query .= "AND group_id = $param ";
                }
            }
        } else {
            if ($group) {
                $param = $this->userMapper->getDatabase()->quote($group);
                $query .= "group_id = $param ";
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
        $query .= "ORDER BY group_id, lyl.sequence, lastname, firstname";
        if (is_numeric($limit)) {
            $query .= " LIMIT $limit";
        }
        $statement = $this->userMapper->getDatabase()->query($query);
        $statement->execute();
        return $statement->fetchAll();
    }
}
