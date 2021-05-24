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
     * @return \Schoolbox\Collection
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function bulkModifySearch($searchCampus, $searchRole, $searchEportfolio, $searchYear)
    {
        $roleMapper = new RoleMapper();
        $campusMapper = new CampusMapper();
        $params = array();

        /* Find search results
         */
        $query = "
            SELECT
                {$this->getColumnString('u')},
                {$roleMapper->getColumnString('g')},
                {$campusMapper->getColumnString('c')}
            FROM {$this->getTable()} u
            LEFT JOIN `groups` g ON u.`group_id` = g.`id`
            LEFT JOIN `userCampus` uc ON uc.`uid` = u.`id`
            LEFT JOIN luCampus c ON c.id = uc.`campus`
            LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
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

        /* Always order by lastname
         */
        $query .= ' ORDER BY u.lastname';

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        $userCollection = new Collection();

        foreach ($statement as $row) {
            if ($userCollection->offsetExists($row['u.id'])) {
                $user = $userCollection->offsetGet($row['u.id']);
            } else {
                $user = $this->hydrate($row, 'u');
                $user->setCampusCollection($campusMapper->createCollection());
                $userCollection->offsetSet($user->getId(), $user);
            }
            $user->setRole($roleMapper->hydrate($row, 'g'));

            $campus = $campusMapper->hydrate($row, 'c');

            /* If the campus object has no id, then it spawned a campus
             * object from nonexistent data (e.g. a user with no campus)
             * Don't add this non-existent campus to the user
             */
            if ($campus instanceof Campus) {
                $user->getCampusCollection()->add($campus);
            }
        }

        return $userCollection;
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
