<?php
/**
 * UserBulkMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;
use Schoolbox;

class UserBulkMapper extends UserMapper {

    /**
     * Value of "Unchanged" option
     *
     * @var int
     */
    private $unchanged = -1;

    /**
     * Cheesy query agglutination
     *
     * @param $searchCampus
     * @param $searchRole
     * @param $searchEportfolio
     * @param $searchYear
     * @return \Schoolbox\Collection
     */
    public function bulkModifySearch($searchCampus, $searchRole, $searchEportfolio, $searchYear) {
        $roleMapper = new RoleMapper();
        $campusMapper = new CampusMapper();

        /* Find search results
         */
        $query = "
            SELECT
                {$this->getColumnString('u')},
                {$roleMapper->getColumnString('g')},
                {$campusMapper->getColumnString('c')}, uc.defaultCampus as `uc.defaultCampus`
            FROM {$this->getTable()} u
            LEFT JOIN `groups` g ON u.`group_id` = g.`id`
            LEFT JOIN `userCampus` uc ON uc.`uid` = u.`id`
            LEFT JOIN luCampus c ON c.id = uc.`campus`
        ";

        /* Augmented where
         */
        $query .= "WHERE 1 = 1";

        if ($searchCampus != $this->getUnchanged()) {
            $query .= "
                AND uc.campus = :searchCampus
            ";

            $params['searchCampus'] = $searchCampus;
        }

        if ($searchRole != $this->getUnchanged()) {
            $query .= "
                AND u.group_id = :searchRole
            ";

            $params['searchRole'] = $searchRole;
        }

        if ($searchEportfolio != $this->getUnchanged()) {
            $query .= "
                AND u.eportfolio_enabled = :searchEportfolio
            ";

            $params['searchEportfolio'] = $searchEportfolio;
        }

        if (strlen(trim($searchYear))) {
            $query .= "
                AND u.year = :searchYear
            ";

            $params['searchYear'] = $searchYear;
        }

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($params);

        $userCollection = new Collection();

        foreach($statement as $row) {
            $user = $this->hydrate($row, 'u');
            $user->setRole($roleMapper->hydrate($row, 'g'));

            $campus = $campusMapper->hydrate($row, 'c');

            /* Put this user in his place
             */
            $userOffset = $userCollection->getOffset($user->getId());

            if (!($userOffset instanceof User)) {
                $userCollection->setOffset($user->getId(), $user);
                $userOffset = $userCollection->getOffset($user->getId());
            }

            /* If the campus object has no id, then it spawned a campus
             * object from nonexistent data (e.g. a user with no campus)
             * In this case, the campus object will be totally empty but
             * still occupy slots in the user's campus collection stack
             */
            if ($campus->getId()) {
                $userOffset->getCampusCollection()->add($campus);

                if ($row['uc.defaultCampus'] == 1) {
                    $user->setDefaultCampus($campus);
                }
            }
        }

        return $userCollection;
    }

    /**
     * Update defaultCampus only, for this user
     *
     * @param $user
     * @param $defaultCampusId
     */
    private function updateDefaultCampusOnly($user, $defaultCampusId) {

        /* Ensure that the user is involved with the defaultCampus,
         * in case you select a default campus that the user isn't
         * currently involved in
         */
        $query = "
            INSERT IGNORE userCampus (uid, campus, defaultCampus)
            VALUES (:userId, :campus, :defaultCampusSetting)
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'userId' => $user->getId(),
            'campus' => $defaultCampusId,
            'defaultCampusSetting' => 0
        ));

        /* Reset defaultCampus to 0 for all campuses
         */
        $query = "
            UPDATE userCampus
            SET defaultCampus = :defaultCampusSetting
            WHERE uid = :userId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'userId' => $user->getId(),
            'defaultCampusSetting' => 0
        ));

        /* Apply new choices
         */
        $query = "
            UPDATE userCampus
            SET defaultCampus = :defaultCampusSetting
            WHERE uid = :userId
            AND campus = :defaultCampusId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'userId' => $user->getId(),
            'defaultCampusId' => $defaultCampusId,
            'defaultCampusSetting' => 1
        ));
    }

    /**
     * Only update campus involvement, leaving the defaultCampus unchanged
     * for this user
     *
     * @param $user
     * @param $allCampusIds
     */
    private function updateCampusInvolvementOnly($user, $allCampusIds) {

        /* Clear existing campuses that aren't the default
         */
        $query = "
            DELETE FROM userCampus
            WHERE uid = :userId
            AND defaultCampus = :defaultCampus
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'userId' => $user->getId(),
            'defaultCampus' => 0
        ));

        /* Apply new choices
         */
        foreach($allCampusIds as $currentCampusId) {
            $query = "
                INSERT IGNORE INTO userCampus (uid, campus, defaultCampus)
                VALUES (:userId, :campus, :defaultCampus)
            ";

            $statement = $this->getDatabase()->prepare($query);
            $statement->execute(array(
                'userId' => $user->getId(),
                'campus' => $currentCampusId,
                'defaultCampus' => 0
            ));
        }
    }

    /**
     * Update defaultCampus and other campus involvement for this user
     *
     * @param $user
     * @param $defaultCampusId
     * @param $allCampusIds
     */
    private function updateAllCampusOptions($user, $defaultCampusId, $allCampusIds) {

        /* Clear existing campus
         */
        $query = "
            DELETE FROM userCampus
            WHERE uid = :userId
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute(array(
            'userId' => $user->getId()
        ));

        /* Apply new choices (ignore Unchanged option)
         */
        foreach($allCampusIds as $currentCampusId) {
            if ($currentCampusId != $this->getUnchanged()) {
                $query = "
                    INSERT INTO userCampus (uid, campus, defaultCampus)
                    VALUES (:userId, :campus, :defaultCampus)
                ";

                $statement = $this->getDatabase()->prepare($query);
                $statement->execute(array(
                    'userId' => $user->getId(),
                    'campus' => $currentCampusId,
                    'defaultCampus' => $currentCampusId == $defaultCampusId ? 1 : 0
                ));
            }
        }
    }

    /**
     * Mass Modify 3
     *
     * @param $user
     * @param $defaultCampusId
     * @param $allCampusIds
     */
    public function bulkModifyUpdate($user, $defaultCampusId, $allCampusIds) {

        /* If a default campus AND a set of campuses were chosen,
         * apply campus updates and then assign the default.
         */
        if (($defaultCampusId != $this->getUnchanged()) && is_array($allCampusIds) && count($allCampusIds)) {
            $campusInvolvementUnchanged = in_array($this->getUnchanged(), array_values($allCampusIds));

            /* Only update the defaultCampus
             */
            if ($campusInvolvementUnchanged) {
                $this->updateDefaultCampusOnly($user, $defaultCampusId);
            }
            /* Update defaultCampus and campus involvement
             */
            else {
                $this->updateAllCampusOptions($user, $defaultCampusId, $allCampusIds);
            }
        }
        /* Only update the defaultCampus
         */
        else if ($defaultCampusId != $this->getUnchanged()) {
            $this->updateDefaultCampusOnly($user, $defaultCampusId);
        }
        /* Otherwise if a set of campuses were chosen, apply campus updates
         * defaultCampus, if unchanged, will always persist in campus choices.
         * This results in the defaultCampus + any other new campuses chosen
         */
        else if (count($allCampusIds)) {
            $this->updateCampusInvolvementOnly($user, $allCampusIds);
        }
    }

    /* Getters/Setters
     */

    /**
     * @param int $unchanged
     */
    private function setUnchanged($unchanged)
    {
        $this->unchanged = $unchanged;
    }

    /**
     * @return int
     */
    private function getUnchanged()
    {
        return $this->unchanged;
    }
}