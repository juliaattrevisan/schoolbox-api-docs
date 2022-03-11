<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Lumberjack\Logger;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Class UserBulkMapper
 *
 * @package Schoolbox
 */
class UserBulkMapper extends UserMapper
{

    /**
     * Value of "Unchanged" option
     *
     * @var int
     */
    private const UNCHANGED = -1;


    /**
     * After bulk processing this will contain a log of all the success and fail results
     *
     * @var string
     */
    private $outputMessage = '';


    /**
     * Bulk modify logger
     * @var Logger
     */
    private $logger;


    /**
     * Construct the log system for bulk logging
     */
    protected function postSetup()
    {
        $file = fopen($this->getConfigLoader()->get('logs_dir') . '/audit.log', 'a');
        $this->logger = new Logger($file, null, 200, [], true);
    }


    /**
     * Cheesy query agglutination
     *
     * @param int      $searchCampus
     * @param int      $searchRole
     * @param string   $searchEportfolio
     * @param int|null $searchYear
     * @param int      $searchToken
     * @return array
     */
    public function bulkModifySearch(int $searchCampus, int $searchRole, string $searchEportfolio, ?int $searchYear, int $searchToken): array
    {
        $params = array();

        /* Find search results
         */
        $query = "
            SELECT
                u.id,
                u.firstname,
                u.lastname,
                u.synergy_id as external_id,
                g.name as role,
                c.name as campus,
                lyl.name as year,
                jwt.jwt_id as token
            FROM {$this->getTable()} u
            LEFT JOIN `groups` g ON u.`group_id` = g.`id`
            LEFT JOIN `userCampus` uc ON uc.`uid` = u.`id`
            LEFT JOIN luCampus c ON c.id = uc.`campus`
            LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
            LEFT JOIN user_jwt_id jwt ON u.id = jwt.user_id AND jwt.deleted_at IS NULL
        ";

        /* Augmented where
         */
        $query .= 'WHERE u.deleted_at is null';

        if ($searchCampus != self::UNCHANGED) {
            $query .= ' AND uc.campus = :searchCampus';

            $params['searchCampus'] = $searchCampus;
        }

        if ($searchRole != self::UNCHANGED) {
            $query .= ' AND u.group_id = :searchRole';

            $params['searchRole'] = $searchRole;
        }

        if ($searchEportfolio != self::UNCHANGED) {
            $query .= ' AND u.eportfolio_enabled = :searchEportfolio';

            $params['searchEportfolio'] = $searchEportfolio;
        }

        if ($searchYear != self::UNCHANGED) {
            if ($searchYear) {
                $query .= ' AND u.year_id = :searchYear';

                $params['searchYear'] = $searchYear;
            } else {
                $query .= ' AND u.year_id IS NULL';
            }
        }

        if ($searchToken != self::UNCHANGED) {
            $query .= ' AND jwt.jwt_id IS ' . ($searchToken ? 'NOT' : '') . ' NULL';
        }

        /* Always order by lastname
         */
        $query .= ' ORDER BY u.lastname';

        $statement = $this->getDatabase()->prepare($query);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute($params);

        $users = [];

        foreach ($statement as $row) {
            if (key_exists($row['id'], $users)) {
                $users[$row['id']]['campuses'][] = $row['campus'];
            } else {
                $user = $row;
                $user['campuses'] = [$row['campus']];
                unset($user['campus']);
                $users[$row['id']] = $user;
            }
        }

        return array_values($users);
    }


    /**
     * Mass-delete of a collection of users, plus logging.
     *
     * @param  User[] $userCollection
     * @return bool
     * @throws MapperException
     */
    public function bulkDelete($userCollection)
    {
        $result = $this->delete($userCollection);
        foreach ($userCollection as $user) {
            $this->log("{$this->getSessionUser()->getFullName()} ({$this->getSessionUser()->getId()}) deleted user {$user->getFullName()} ({$user->getId()})");
        }
        return $result;
    }


    /**
     * Log to file
     *
     * @param string $message
     */
    public function log($message)
    {
        $this->logger->info($message);
        $this->outputMessage .= $message . "\n";
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getOutputMessage(): string
    {
        return $this->outputMessage;
    }
}
