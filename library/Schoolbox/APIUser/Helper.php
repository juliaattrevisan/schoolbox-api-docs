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
     * get the count of users from the search query, irrespective fo the limit, or cursor location
     * @param string|null $keyword
     * @param array|null  $filter
     * @return int
     */
    public function ajaxSearchCount(
        ?string $keyword,
        ?array $filter
    ) {
        [$query, $params] = $this->buildSearch($keyword, $filter);
        $statement = $this->userMapper->getDatabase()->prepare($query);
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * Find a user based on a keyword, for an AJAX response.
     * stripped and updated from `library/User.php`
     *
     * @param string|null $keyword
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
        [$query, $params] = $this->buildSearch($keyword, $filter);

        if (0 !== $cursor) {
            $query .= " AND user.id > :cursor ";
        }
        $query .= "ORDER BY user.id, lyl.sequence, lastname, given_name";
        if (0 !== $limit) {
            $query .= " LIMIT :limit";
        }

        $statement = $this->userMapper->getDatabase()->prepare($query);
        if (0 !== $limit) {
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        if (0 !== $cursor) {
            $statement->bindValue('cursor', $cursor, PDO::PARAM_INT);
        }
        foreach ($params as $label => $value) {
            $statement->bindValue($label, $value);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    /**
     * @param string|null $keyword
     * @param array|null  $filter
     * @return array [sqlQuery, params]
     */
    private function buildSearch(?string $keyword, ?array $filter)
    {
        $username   = $filter['username'] ?? '';
        $externalId = $filter['externalId'] ?? '';
        $email      = $filter['email'] ?? '';
        $roleType   = $filter['roleType'] ?? '';
        $role       = $filter['role'] ?? '';
        $campus     = $filter['campus'] ?? '';
        $year       = $filter['year'] ?? '';

        //search for a user based on at least one of the above fields
        //assumes at least one contains something
        $select = '
            SELECT
                user.id AS uid,
                title,
                firstname,
                preferred_name,
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
                    $params['firstName'] = $params['preferredName'] =
                        '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[1]) . '%';
                    $params['lastName']  =
                        '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($name[2]) . '%';
                    $where .= "AND (
                        (firstname like :firstName or preferred_name like :preferredName)
                        and lastname like :lastName
                    ) ";
                } else {
                    $params['username'] = $params['lastName'] = $params['firstName'] = $params['preferredName'] =
                        '%' . $this->userMapper->getDatabase()->getQuoter()->escapeForLike($keyword) . '%';
                    $where .= "AND (
                        firstname like :firstName
                        or preferred_name like :preferredName
                        or lastname like :lastName
                        or username like :username
                    ) ";
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
        $query = $select . $from . $where;
        return [$query, $params];
    }
}
