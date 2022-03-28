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
     * @param string     $keyword
     * @param array|null $filter
     * @param int|null   $limit
     * @return array|bool
     * @throws \Exception
     */
    public function ajaxSearch(
        string $keyword,
        ?array $filter,
        ?int $limit = null
    ) {
        $username   = $filter['username'] ?? '';
        $externalId = $filter['externalId'] ?? '';
        $email      = $filter['email'] ?? '';
        $roleType   = $filter['roleType'] ?? '';
        $role       = $filter['role'] ?? '';
        $campus     = $filter['campus'] ?? '';
        $year       = $filter['year'] ?? '';
        $group      = $filter['group'] ?? null;

        //search for a user based on at least one of the above fields
        //assumes at least one contains something
        $select = '
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
                synergy_id';
        $from = ' FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id ';
        $where = ' WHERE user.deleted_at IS NULL
            AND ';

        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $param = $this->userMapper->getDatabase()->quote($keyword);
                $where .= "( synergy_id = $param  ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
//                    $firstName = $this->userMapper->getDatabase()->quote($name[1]);
//                    $lastName  = $this->userMapper->getDatabase()->quote($name[2]);
//                    $where .= "( firstname like '%{$firstName}%' and lastname like '%{$lastName}%' ) ";
                    $where .= "( firstname like '%{$name[1]}%' and lastname like '%{$name[2]}%' ) ";
                } else {
//                    $param = $this->userMapper->getDatabase()->quote($keyword);
                    $where .= "( firstname like '%$keyword%' or lastname like '%$keyword%' or username like '%$keyword%' ) ";
                }
            }
        }
        if ('' !== $username) {
            $where .= "AND username = {$this->userMapper->getDatabase()->quote($username)} ";
        }
        if ('' !== $externalId) {
            $where .= "AND synergy_id = {$this->userMapper->getDatabase()->quote($externalId)} ";
        }
        if ('' !== $email) {
            $where .= "AND alt_email = {$this->userMapper->getDatabase()->quote($email)} ";
        }
        if ('' !== $roleType) {
            $where .= "AND groups.group_type = {$this->userMapper->getDatabase()->quote($roleType)} ";
        }
        if ('' !== $role) {
            $param = $this->userMapper->getDatabase()->quote($role);
            $where .= "AND groups.name = $param ";
        }
        if ('' !== $campus) {
            $select .= "";
            $from .= " JOIN userCampus on userCampus.uid = user.id
                    JOIN luCampus on luCampus.id = userCampus.campus
            ";
            $where .= "AND luCampus.name = {$this->userMapper->getDatabase()->quote($campus)} ";
        }
        if ('' !== $year) {
            $where .= "AND lyl.name = {$this->userMapper->getDatabase()->quote($year)} ";
        }
        $where .= "ORDER BY group_id, lyl.sequence, lastname, firstname";
        if (is_numeric($limit)) {
            $where .= " LIMIT $limit";
        }
        $statement = $this->userMapper->getDatabase()->query($select . $from . $where);
        $statement->execute();
        return $statement->fetchAll();
    }
}
