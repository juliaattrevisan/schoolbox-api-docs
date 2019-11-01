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
                $result['results'] = $this->getUserData($userIds);
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
     * @param string $keyword
     * @param User   $viewer
     *
     * @return array
     */
    private function getUserIdsByKeyword(string $keyword, User $viewer): array
    {
        $statement = $this->db->prepareQuery(
            'SELECT id FROM user ' . $this->getWhereStatement($viewer) .
            ' ORDER BY lastname, firstname ASC LIMIT 0, ' . self::AUTOSUGGEST_LIMIT
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
     * @param User   $viewer
     *
     * @return int
     */
    private function getTotalCount(string $keyword, User $viewer): int
    {
        $statement = $this->db->prepareQuery(
            'SELECT COUNT(*) FROM user ' . $this->getWhereStatement($viewer)
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
     * @param User $viewer
     *
     * @return string
     */
    private function getWhereStatement(User $viewer): string
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
        if (!$this->config->get('show_disabled_users')) {
            $whereStatement .= ' AND user.enabled = 1';
        }

        return $whereStatement;
    }
}
