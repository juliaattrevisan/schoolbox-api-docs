<?php
/**
 * UserBulkMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use \Alaress\Lumberjack\Logger;
use \Schoolbox\Mapper\Exception as MapperException;

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
     * @param $searchCampus
     * @param $searchRole
     * @param $searchEportfolio
     * @param $searchYear
     * @return \Schoolbox\Collection
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

        if (strlen(trim($searchYear))) {
            $query .= ' AND u.year = :searchYear';

            $params['searchYear'] = $searchYear;
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
     * Update campus involvement for this user
     *
     * @param User  $user
     * @param array $allCampusIds
     */
    private function assignCampuses(User $user, $allCampusIds)
    {
        /* Clear existing campus
         */
        $user->setCampusCollection(new GenericCollection(Campus::class));

        /* Apply new choices (ignore Unchanged option)
         */
        foreach ($allCampusIds as $currentCampusId) {
            if ($currentCampusId != self::UNCHANGED) {
                $user->getCampusCollection()->add(CampusMapper::getInstance()->findById($currentCampusId));
            }
        }
    }

    /**
     * Mass Modify 3
     *
     * @param User $user
     * @param      $allCampusIds
     * @param      $roleId
     * @param      $year
     * @param      $eportfolioEnabled
     * @param      $emailFromUsername
     * @param      $externalMailClient
     * @param      $webmail
     * @param      $enabled
     * @param      $hideContacts
     * @param      $hideTimetable
     * @param      $positionTitle
     * @param      $displayGradeGraph
     */
    public function bulkModifyUpdate(
        User $user,
        $allCampusIds,
        $roleId,
        $year,
        $eportfolioEnabled,
        $emailFromUsername,
        $externalMailClient,
        $webmail,
        $enabled,
        $hideContacts,
        $hideTimetable,
        $positionTitle,
        $displayGradeGraph
    ) {

        /* Update group settings
         */
        if ($roleId != self::UNCHANGED) {
            try {
                /** @var Role $role */
                $role = RoleMapper::getInstance()->findById($roleId);
                $user->setRole($role);
            } catch (MapperException $e) {
                $message = "{$user->getFullName()} ({$user->getId()}) was skipped - unknown role";
                $this->log($message);
                return;
            }
        }

        /* Update year settings
         */
        $newYear = trim($year);

        if (strlen($newYear)) {
            $user->setYear($newYear);
        }

        /* Update position title settings
         */
        $newPositionTitle = trim($positionTitle);

        if (strlen($newPositionTitle)) {
            $user->setPositionTitle($newPositionTitle);
        }

        /* Update eportfolio settings
         */
        if ($eportfolioEnabled != self::UNCHANGED) {
            $user->setEportfolioEnable($eportfolioEnabled);
        }

        /* Update emailFromUsername settings
         */
        if ($emailFromUsername != self::UNCHANGED) {
            $user->setLocalEmail($emailFromUsername);
        }

        /* Update externalMailClient settings
         */
        if ($externalMailClient != self::UNCHANGED) {
            $user->setUseInternalMail($externalMailClient);
        }

        /* Update webmail settings
         */
        if ($webmail != self::UNCHANGED) {
            $user->setWebmail($webmail);
        }

        /* Update enabled settings
         */
        if ($enabled != self::UNCHANGED) {
            $user->setEnabled($enabled);
        }

        /* Update hide contacts settings
         */
        if ($hideContacts != self::UNCHANGED) {
            $user->setHideContacts($hideContacts);
        }

        /* Update hide timetable settings
         */
        if ($hideTimetable != self::UNCHANGED) {
            $user->setHideTimetable($hideTimetable);
        }

        /* Update display grade average settings
         */
        if ($displayGradeGraph != self::UNCHANGED) {
            $user->setDisplayGradeGraph($displayGradeGraph);
        }

        /* Update campus settings
         */
        $campusInvolvementChanged = \is_array($allCampusIds)
            && \count($allCampusIds)
            && !\in_array(self::UNCHANGED, array_values($allCampusIds), false);

        if ($campusInvolvementChanged) {
            $this->assignCampuses($user, $allCampusIds);
        }

        try {
            $this->save($user);
            $message = "{$this->getSessionUser()->getFullName()} ({$this->getSessionUser()->getId()}) modified user {$user->getFullName()} ({$user->getId()})";
        } catch (\UnexpectedValueException $e) {
            $message = "{$this->getSessionUser()->getFullName()} ({$this->getSessionUser()->getId()}) failed to modified user {$user->getFullName()} ({$user->getId()}): {$e->getMessage()}";
        } catch (Mapper\Exception $e) {
            $message = "{$this->getSessionUser()->getFullName()} ({$this->getSessionUser()->getId()}) failed to modified user {$user->getFullName()} ({$user->getId()}): {$e->getMessage()}";
        }

        $this->log($message);
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
