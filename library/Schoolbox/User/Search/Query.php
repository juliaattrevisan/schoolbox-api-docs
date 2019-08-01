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
     * Minimum search length before the query qet's executed
     */
    public const MIN_SEARCH_LENGTH = 3;

    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $minSearchLength;

    /**
     * @var string
     */
    private $sortBy;

    /**
     * @var string
     */
    private $sortMethod;

    /**
     * Query constructor.
     *
     * @param IConnector $db
     * @param int        $limit
     * @param int        $minSearchLength
     * @param string     $sortBy
     * @param string     $sortMethod
     */
    public function __construct(
        IConnector $db,
        int $limit = self::AUTOSUGGEST_LIMIT,
        int $minSearchLength = self::MIN_SEARCH_LENGTH,
        string $sortBy = 'lastname',
        string $sortMethod = 'ASC'
    ) {
        $this->db               = $db;
        $this->limit            = $limit;
        $this->minSearchLength  = $minSearchLength;
        $this->sortBy           = $sortBy;
        $this->sortMethod       = $sortMethod;
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

        if ($keyword !== '' && \strlen($keyword) >= $this->minSearchLength && $this->getUserIdsByKeyword($keyword)) {
            $result['results']      = $this->getUserData($this->getUserIdsByKeyword($keyword));
            $result['totalCount']   = $this->getTotalCount($keyword);
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
            $result[$key]['formattedName']  = $user->formatName();
            $result[$key]['metadata']       = $user->formatMetadata();
            $result[$key]['year']           = $user->getYear();
        }

        return $result;
    }

    /**
     * @param string $keyword
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword): array
    {
        return $this->executeQuery(
            'SELECT id FROM user' . $this->getWhereStatement() . ' ORDER BY ' . $this->sortBy . ' ' . $this->sortMethod . ' LIMIT 0 , ' . $this->limit,
            '%' . $keyword . '%'
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @param string $keyword
     *
     * @return int
     */
    private function getTotalCount(string $keyword): int
    {
        return (int) $this->executeQuery('SELECT COUNT(*) FROM user' . $this->getWhereStatement(), '%' . $keyword . '%')->fetchColumn();
    }

    /**
     * @return string
     */
    private function getWhereStatement(): string
    {
        $whereStatement = ' WHERE CONCAT_WS(" ",title,firstname,lastname) LIKE :firstAndLastName 
        OR CONCAT_WS(" ",title,lastname) LIKE :lastname 
        OR username LIKE :username AND user.deleted_at IS NULL';

        if (!ConfigMapper::getInstance()->get('show_disabled_users')) {
            $whereStatement .= ' AND user.enabled = 1';
        }

        return $whereStatement;
    }

    /**
     * @param string $queryString
     * @param string $keyword
     *
     * @return \Alaress\Database\Statement\Query\IQuery
     */
    private function executeQuery(string $queryString, string $keyword)
    {
        $statement = $this->db->prepareQuery($queryString);
        $statement->execute([
            'firstAndLastName'  => $keyword,
            'lastname'          => $keyword,
            'username'          => $keyword
        ]);

        return $statement;
    }
}
