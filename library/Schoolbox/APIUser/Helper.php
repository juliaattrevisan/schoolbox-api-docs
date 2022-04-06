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
        $params = [];
        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $params['externalId'] = $this->userMapper->getDatabase()->quote($keyword);
                $where .= "( synergy_id = :externalId  ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $params['firstName'] = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[1]) . '%';
                    $params['lastName']  = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[2]) . '%';
                    $where .= "( firstname like :firstName and lastname like :lastName ) ";
                } else {
                    $params['username'] = $params['lastName'] = $params['firstName'] = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($keyword) . '%';
                    $where .= "( firstname like :firstName or lastname like :lastName or username like :username ) ";
                }
            }
        }
        if ('' !== $username) {
            $params['usernameExac'] = $this->userMapper->getDatabase()->quote($username);
            $where .= "AND username = :usernameExac ";
        }
        if ('' !== $externalId) {
            $params['externalIdExac'] = $this->userMapper->getDatabase()->quote($externalId);
            $where .= "AND synergy_id = :externalIdExac ";
        }
        if ('' !== $email) {
            $params['email'] = $this->userMapper->getDatabase()->quote($email);
            $where .= "AND alt_email = :email ";
        }
        if ('' !== $roleType) {
            $params['roleType'] = $this->userMapper->getDatabase()->quote($roleType);
            $where .= "AND groups.group_type = :roleType ";
        }
        if ('' !== $role) {
            $params['role'] = $this->userMapper->getDatabase()->quote($role);
            $where .= "AND groups.name = :role ";
        }
        if ('' !== $campus) {
            $select .= "";
            $from .= " JOIN userCampus on userCampus.uid = user.id
                    JOIN luCampus on luCampus.id = userCampus.campus
            ";
            $params['campus'] = $this->userMapper->getDatabase()->quote($campus);
            $where .= "AND luCampus.name = :campus ";
        }
        if ('' !== $year) {
            $params['year'] = $this->userMapper->getDatabase()->quote($year);
            $where .= "AND lyl.name = :year ";
        }
        $where .= "ORDER BY group_id, lyl.sequence, lastname, firstname";
        if (is_numeric($limit)) {
            $where .= " LIMIT $limit";
        }
        $stat = $select . $from . $where;
        $statement = $this->userMapper->getDatabase()->prepare($stat);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
