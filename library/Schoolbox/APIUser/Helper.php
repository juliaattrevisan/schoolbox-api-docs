<?php

namespace Schoolbox\APIUser;

use PDO;
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
     * @param int|null   $cursor
     * @return array|bool
     * @throws \Exception
     */
    public function ajaxSearch(
        ?string $keyword,
        ?array $filter,
        ?int $limit = null,
        ?int $cursor = null
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
                `groups`.name AS gname,
                `groups`.id AS gid,
                lyl.name AS year,
                local_email,
                alt_email,
                synergy_id';
        $from = '
            FROM user
                LEFT JOIN `groups` ON user.group_id = `groups`.id
                LEFT JOIN lu_year_level lyl ON user.year_id = lyl.id ';
        $where = '
            WHERE user.deleted_at IS NULL
            ';
        $params = [];
        if ($keyword != "") {
            if (is_numeric($keyword)) {
                $params['externalId'] = $keyword;
                $where .= "AND ( synergy_id = :externalId  ) ";
            } else {
                if (preg_match('/([[:alpha:]\'"]+) ([[:alpha:]\'"]+)/', $keyword, $name)) {
                    $params['firstName'] = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[1]) . '%';
                    $params['lastName']  = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[2]) . '%';
                    $where .= "AND ( firstname like :firstName and lastname like :lastName ) ";
                } else {
                    $params['username'] = $params['lastName'] = $params['firstName'] = '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($keyword) . '%';
                    $where .= "AND ( firstname like :firstName or lastname like :lastName or username like :username ) ";
                }
            }
        }
        if ('' !== $username) {
            $params['usernameExac'] = $username;
            $where .= "AND username = :usernameExac ";
        }
        if ('' !== $externalId) {
            $params['externalIdExac'] = $externalId;
            $where .= "AND synergy_id = :externalIdExac ";
        }
        if ('' !== $email) {
            $params['email'] = $email;
            $where .= "AND if( user.local_email , CONCAT(user.username, '@', (SELECT value from config where name = 'mail_address')), alt_email) = :email ";
        }
        if ('' !== $roleType) {
            $params['roleType'] = $roleType;
            $where .= "AND `groups`.group_type = :roleType ";
        }
        if ('' !== $role) {
            if (array_key_exists('name', $role)) {
                $params['role'] = $role['name'];
                $where          .= "AND `groups`.name = :role ";
            } elseif (array_key_exists('id', $role)) {
                $params['role'] = $role['id'];
                $where          .= "AND `groups`.id = :role ";
            }
        }
        if ('' !== $campus) {
            if (array_key_exists('name', $campus)) {
                $select           .= "";
                $from             .= " JOIN userCampus on userCampus.uid = user.id
                    JOIN luCampus on luCampus.id = userCampus.campus
            ";
                $params['campus'] = $campus['name'];
                $where            .= "AND luCampus.name = :campus ";
            } elseif (array_key_exists('id', $campus)) {
                $select           .= "";
                $from             .= " JOIN userCampus on userCampus.uid = user.id
            ";
                $params['campus'] = $campus['id'];
                $where            .= "AND userCampus.campus = :campus ";
            }
        }
        if ('' !== $year) {
            if (array_key_exists('name', $year)) {
                $params['year'] = $year['name'];
                $where          .= "AND lyl.name = :year ";
            } elseif (array_key_exists('id', $year)) {
                $params['year'] = $year['id'];
                $where          .= "AND lyl.id = :year ";
            }
        }
        if (0 !== $cursor) {
            $where .= " AND user.id > :cursor ";
        }
        $where .= "ORDER BY user.id, lyl.sequence, lastname, firstname";
        if (0 !== $limit) {
            $where .= " LIMIT :limit";
        }
        $stat = $select . $from . $where;
        $statement = $this->userMapper->getDatabase()->prepare($stat);
        if (0 !== $limit) {
            $lim = $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        if (0 !== $cursor) {
            $cur = $statement->bindValue('cursor', $cursor, PDO::PARAM_INT);
        }
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();
        return $statement->fetchAll();
    }
}
