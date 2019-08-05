<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\User\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\UserMapper;

class Query
{
    /**
     * Number of users that will show on autosuggest field
     */
    public const AUTOSUGGEST_LIMIT = 5;

    /**
     * Minimum search length before the query get's executed
     */
    public const MIN_SEARCH_LENGTH = 3;

    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var int
     */
    protected $limit = self::AUTOSUGGEST_LIMIT;

    /**
     * Query constructor.
     *
     * @param IConnector $db
     */
    public function __construct(IConnector $db)
    {
        $this->db = $db;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param string $keyword
     *
     * @return array
     */
    public function fetchResults(string $keyword): array
    {
        $keyword = trim($keyword);

        $result = [
            'results'       => [],
            'totalCount'    => 0,
        ];

        if ($keyword !== '' && \strlen($keyword) >= $this->minSearchLength) {
            $searchString = '%' . $keyword . '%';
            if ($this->getUserIdsByKeyword($searchString)) {
                $result['results'] = $this->getUserData($this->getUserIdsByKeyword($searchString));
                $result['totalCount'] = $this->getTotalCount($searchString);
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

        $users = UserMapper::getInstance()->findByColumn('id', $userIds);
        
        foreach ($users as $key => $user) {
            $result[$key]['id']             = $user->getId();
            $result[$key]['externalId']     = $user->getExternalId();
            $result[$key]['firstname']      = $user->getFirstname();
            $result[$key]['lastname']       = $user->getLastname();
            $result[$key]['formattedName']  = $user->formatName();
            $result[$key]['metadata']       = $user->formatMetadata();
            $result[$key]['year']           = $user->getYear();
        }

        /**
         * Sort the result by lastname and firstname
         */
        array_multisort(
           array_column($result, 'lastname'), SORT_ASC,
            array_column($result, 'firstname'), SORT_ASC,
            $result
        );

        return $result;
    }

    /**
     * @param string $keyword
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword): array
    {
        $statement = $this->db->prepareQuery(
            'SELECT id FROM user ' . $this->getWhereStatement() .
            ' ORDER BY firstname, lastname ASC LIMIT 0, ' . $this->limit
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
     * @param string $keyword
     *
     * @return int
     */
    private function getTotalCount(string $keyword): int
    {
        $statement = $this->db->prepareQuery(
            'SELECT COUNT(*) FROM user ' . $this->getWhereStatement()
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
     * @return string
     */
    private function getWhereStatement(): string
    {
        $whereStatement = ' WHERE CONCAT_WS(" ",title,firstname,lastname) LIKE :firstAndLastName 
        OR CONCAT_WS(" ",title,lastname) LIKE :lastname 
        OR username LIKE :username 
        OR soundex(firstname) = soundex(:sfirstname) 
        OR soundex(lastname) = soundex(:slastname) 
        AND user.deleted_at IS NULL ';

        /**
         * When show_disabled_users flag is set to TRUE, disabled users will be shown in the result
         * Otherwise, only active users will be shown
         */
        if (!ConfigMapper::getInstance()->get('show_disabled_users')) {
            $whereStatement .= ' AND user.enabled = 1';
        }

        return $whereStatement;
    }
}
