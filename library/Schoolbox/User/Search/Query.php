<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\User\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Query
{
    /**
     * Number of users that will show on autosuggest field
     */
    public const AUTOSUGGEST_LIMIT = 5;

    public const MIN_SEARCH_LENGTH = 2;

    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var IConfigLoader
     */
    private $config;

    /**
     * Query constructor.
     *
     * @param IConnector    $db
     * @param IConfigLoader $config
     */
    public function __construct(IConnector $db, IConfigLoader $config)
    {
        $this->db     = $db;
        $this->config = $config;
    }

    /**
     * Find the search results.
     *
     * @param string $keyword What's the keyword `$viewer` is searching for?
     * @param User   $viewer  Who's viewing the results? This affects what they
     *                        may see.
     *
     * @return array
     */
    public function fetchResults(string $keyword, User $viewer): array
    {
        $keyword = trim($keyword);

        $result = [
            'results'       => [],
            'totalCount'    => 0,
        ];

        if ($keyword !== '' && \strlen($keyword) >= self::MIN_SEARCH_LENGTH) {
            $searchString = '%' . $keyword . '%';
            $userIds = $this->getUserIdsByKeyword($searchString, $viewer);
            if ($userIds) {
                $result['results']    = $this->getUserData($userIds);
                $result['totalCount'] = $this->getTotalCount($searchString, $viewer);
            }
        }

        return $result;
    }

    /**
     * @param array $userIds
     *
     * @return array
     */
    private function getUserData(array $userIds): array
    {
        $result = [];

        $users = UserMapper::getInstance()->findByColumn('id', $userIds, ['order'=>['lastname'=>'ASC', 'firstname' => 'ASC']]);

        foreach ($users as $key => $user) {
            $result[$key]['id']             = $user->getId();
            $result[$key]['externalId']     = $user->getExternalId();
            $result[$key]['firstname']      = $user->getFirstname();
            $result[$key]['lastname']       = $user->getLastname();
            $result[$key]['formattedName']  = $user->formatName();
            $result[$key]['metadata']       = $user->formatMetadata();
            $result[$key]['year']           = $user->getYear();
        }

        return $result;
    }

    /**
     * Find IDs for users that match the given keyword.
     *
     * This list is subject to filtering in `getWhereStatement`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword, User $viewer): array
    {
        $statement = $this->db->prepareQuery(
            'SELECT DISTINCT u.id FROM user u INNER JOIN `groups` g ON u.group_id = g.id'
            . $this->getWhereStatement($viewer)
            . ' ORDER BY u.lastname, u.firstname ASC LIMIT 0, ' . self::AUTOSUGGEST_LIMIT
        );

        $statement->execute([
            'firstAndLastName'  => $keyword,
            'lastname'          => $keyword,
            'username'          => $keyword,
            'sfirstname'        => $keyword,
            'slastname'         => $keyword,
        ]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Find a total count of users that match the given keyword.
     *
     * This count is subject to filtering in `getWhereStatement`.
     *
     * @param string $keyword
     * @param User   $viewer
     *
     * @return int
     */
    private function getTotalCount(string $keyword, User $viewer): int
    {
        $statement = $this->db->prepareQuery(
            'SELECT COUNT(DISTINCT u.id) FROM user u INNER JOIN `groups` g ON u.group_id = g.id'
            . $this->getWhereStatement($viewer)
        );

        $statement->execute([
            'firstAndLastName'  => $keyword,
            'lastname'          => $keyword,
            'username'          => $keyword,
            'sfirstname'        => $keyword,
            'slastname'         => $keyword,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Build the WHERE statement which filters the list and count of users
     * found by the search.
     *
     * @param User $viewer
     *
     * @return string
     */
    private function getWhereStatement(User $viewer): string
    {
        $whereParts = [
            '
                CONCAT_WS(" ", u.title, u.firstname, u.lastname) LIKE :firstAndLastName
                OR CONCAT_WS(" ", u.title, u.lastname) LIKE :lastname
                OR u.username LIKE :username
                OR soundex(u.firstname) = soundex(:sfirstname)
                OR soundex(u.lastname) = soundex(:slastname)
            ',
            'u.deleted_at IS NULL'
        ];

        /**
         * When show_disabled_users flag is set to TRUE, disabled users will be shown in the result
         * Otherwise, only active users will be shown
         */
        if (!$this->config->get('show_disabled_users')) {
            $whereParts[] = 'u.enabled = 1';
        }

        // Staff may always view parents and students
        // Guests may never view parents and students
        // Parents and students may only view parents or students if the config
        // allows it
        if ($viewer->getRole()->isGuest()) {
            $findStudents = false;
            $findParents  = false;
        } elseif ($viewer->getRole()->isStaff()) {
            $findStudents = true;
            $findParents  = true;
        } else {
            $findStudents = $this->config->get('student_search_enable');
            $findParents  = $this->config->get('parent_search_enable');
        }

        if (!$findStudents) {
            $whereParts[] = "g.group_type != 'student'";
        }
        if (!$findParents) {
            $whereParts[] = "g.group_type != 'parent'";
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
    }
}
