<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\User\Search;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Config\ConfigMapper;

class Query
{
    /**
     * @var IConnector
     */
    private $db;

    /**
     * @var string
     */
    private $mainQuery;

    /**
     * @var string
     */
    private $countQuery;

    /**
     * @var string
     */
    private $keyword = '';

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
     * @param string $keyword
     * @param int    $limit
     * @param string $sortBy
     * @param string $sortMethod
     *
     * @return Query
     */
    public function buildQuery(string $keyword, int $limit = 5, string $sortBy = 'lastname', string $sortMethod = 'ASC'): self
    {
        $this->keyword = $keyword;

        $joinStatement = 'FROM `user`
            LEFT JOIN `groups` ON `user`.group_id = `groups`.id
            LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
            LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id';

        $whereStatement = ' WHERE CONCAT_WS(" ",title,firstname,lastname) LIKE :firstAndLastName 
        OR CONCAT_WS(" ",title,lastname) LIKE :lastname 
        OR username LIKE :username AND user.deleted_at IS NULL';

        $this->mainQuery = 'SELECT 
            user.id,
            user.username,
            CONCAT(user.title, \' \', user.firstname, \' \', user.lastname) AS full_name,    
            CONCAT(\'/portrait.php?userId=\', user.synergy_id, \'&size=\') AS portrait,
            user.year,
            user.synergy_id AS external_id, 
            groups.name AS group_name, 
            luCampus.name AS campus_name ' . $joinStatement . $whereStatement .
            ' ORDER BY ' . $sortBy . ' ' . $sortMethod . ' LIMIT 0 , ' . $limit;


        $this->countQuery = 'SELECT COUNT(*) AS totalCount ' . $joinStatement . $whereStatement;

        return $this;
    }

    /**
     * @return array
     */
    public function getResults()
    {
        return [
            'results'       => $this->executeQuery($this->mainQuery, $this->keyword)->fetchAll(),
            'totalCount'    => $this->executeQuery($this->countQuery, $this->keyword)->fetchColumn()
        ];
    }

    /**
     * @param string $queryString
     *
     * @return \Alaress\Database\Statement\Query\IQuery
     */
    private function executeQuery(string $queryString)
    {
        $keyword = '%' . $this->keyword . '%';

        $statement = $this->db->prepareQuery($queryString);
        $statement->execute([
            'firstAndLastName'  => $keyword,
            'lastname'          => $keyword,
            'username'          => $keyword
        ]);

        return $statement;
    }

    /**
     * @return string
     */
    private function buildMainQuery(): string
    {
        return 'SELECT 
            user.id,
            user.username,
            CONCAT(user.title, \' \', user.firstname, \' \', user.lastname) AS full_name,           
            user.year,
            user.synergy_id AS external_id, 
            groups.name AS group_name, 
            luCampus.name AS campus_name ';
    }

    /**
     * @return string
     */
    private function buildFromAndJoinQuery(): string
    {
        return ' FROM `user`
            LEFT JOIN `groups` ON `user`.group_id = `groups`.id
            LEFT JOIN `userCampus` ON `user`.id = `userCampus`.uid AND defaultCampus = 1
            LEFT JOIN `luCampus` ON `userCampus`.campus = `luCampus`.id\' ';
    }

    /**
     * @return string
     */
    private function buildWherePart(): string
    {
        $queryString = ' CONCAT_WS(\' \',title,firstname,lastname) LIKE :firstAndLastName 
        OR CONCAT_WS(\' \',title,lastname) LIKE :lastname 
        OR username LIKE :username ';

        if (!ConfigMapper::getInstance()->get('show_disabled_users')) {
            $queryString .= ' AND `user`.enabled = 1 ';
        }

        return $queryString;
    }

}
