<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Database\Predicate\Builder;
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
    public const ANY = -1;


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
        $file         = fopen($this->getConfigLoader()->get('logs_dir') . '/audit.log', 'a');
        $this->logger = new Logger($file, null, 200, [], true);
    }


    /**
     * Cheesy query agglutination
     *
     * @param array $searchCampusIds
     * @param array $searchRoleIds
     * @param int   $searchEportfolio
     * @param array $searchYearIds
     * @param int   $searchToken
     * @return array
     */
    public function bulkModifySearch(array $searchCampusIds, array $searchRoleIds, int $searchEportfolio, array $searchYearIds, int $searchToken): array
    {
        $params = [];

        /* Find search results
         */
        $query = "
            SELECT
                u.id,
                u.firstname,
                u.lastname,
                u.synergy_id as external_id,
                u.eportfolio_enabled,
                g.name as role,
                c.name as campus,
                lyl.name as year,
                (jwt.jwt_id IS NOT NULL) as token
            FROM {$this->getTable()} u
            LEFT JOIN `groups` g ON u.`group_id` = g.`id` AND g.deleted_at IS NULL
            LEFT JOIN `userCampus` uc ON uc.`uid` = u.`id`
            LEFT JOIN luCampus c ON c.id = uc.`campus` AND c.deleted_at IS NULL
            LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id AND lyl.deleted_at IS NULL
            LEFT JOIN user_jwt_id jwt ON u.id = jwt.user_id AND jwt.deleted_at IS NULL
        ";

        /* Augmented where
         */
        $whereList = ['u.deleted_at is null'];

        if ($searchCampusIds !== [self::ANY]) {
            $campusBindings = Builder::getBindings($searchCampusIds, 'cm');
            $whereList[]    = " uc.campus IN ({$campusBindings->getPlaceholder()}) ";
            $params         = array_merge($params, $campusBindings->getParams());
        }

        if ($searchRoleIds !== [self::ANY]) {
            $roleBindings = Builder::getBindings($searchRoleIds, 'cm');
            $whereList[]  = " u.group_id IN ({$roleBindings->getPlaceholder()}) ";
            $params       = array_merge($params, $roleBindings->getParams());
        }

        if ($searchEportfolio !== self::ANY) {
            $whereList[] = ' u.eportfolio_enabled = :searchEportfolio ';

            $params['searchEportfolio'] = $searchEportfolio;
        }

        if ($searchYearIds !== [self::ANY]) {
            if (!empty($searchYearIds)) {
                $addNull = false;
                if (in_array(0, $searchYearIds)) {
                    $searchYearIds = array_diff($searchYearIds, [0]);
                    $addNull       = true;
                }
                $yearBindings = Builder::getBindings($searchYearIds, 'sy');
                $where        = " u.year_id IN ({$yearBindings->getPlaceholder()})";
                $params       = array_merge($params, $yearBindings->getParams());
                if ($addNull) {
                    $where = " ({$where} OR ' u.year_id IS NULL' ) ";
                }
                $whereList[] = $where;
            } else {
                $whereList[] = ' u.year_id IS NULL';
            }
        }

        if ($searchToken !== self::ANY) {
            $whereList[] = ' jwt.jwt_id IS ' . ($searchToken ? 'NOT' : '') . ' NULL';
        }

        $query .= ' WHERE ' . implode(' AND ', $whereList) . ' ORDER BY u.lastname';

        $statement = $this->getDatabase()->prepare($query);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute($params);

        $users = [];

        foreach ($statement as $row) {
            if (key_exists($row['id'], $users)) {
                $users[$row['id']]['campuses'][] = $row['campus'];
            } else {
                $user             = $row;
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
     * @param User[] $userCollection
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
