<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Synchronise_External_Fs_Strategy_Abstract extends Synchronise_External_Strategy_Abstract {

    const ADMIN_GROUP_ID   = 1;
    const STAFF_GROUP_ID   = 2;
    const STUDENT_GROUP_ID = 17;
    const PARENT_GROUP_ID  = 4;

    public function __construct($csvFile, $columns) {
        parent::__construct($csvFile, $columns);
        $this->setVerboseFilter(new Synchronise_External_Fs_Verbose());
    }

    /**
     * Identical algorithm used to create/update a user
     *
     * @param  $newUser
     * @return void
     */
    protected function executeCreateUpdate($newUser) {

        // Prepare verbosity
        $this->getVerboseFilter()->setNewUser($newUser);

        // Does this person have a username? If not, skip them
        if ($this->hasUsername($newUser)) {
            $existing = $this->userExistsBySynergyAndGroup($newUser);

            // If the current user exists in Schoolbox, update them
            if ($existing) {
                // Perform action; report what happened
                if ($this->getPerformUpdate() === true) {
                    $newUser['id'] = $existing['uid'];

                    /* Don't change staff groups */
                    if($newUser['group'] == self::STAFF_GROUP_ID)
                        unset($newUser['group']);

                    $this->getUserHandler()->update($newUser);
                    $this->getVerboseFilter()->performUpdate();
                    $this->getVerboseFilter()->incrementUpdated();
                }
                // Report intentions
                else {
                    $this->getVerboseFilter()->planUpdate();
                    $this->getVerboseFilter()->incrementUpdated();
                }
            }
            // Otherwise create them
            else {
                // Perform action; report what happened
                if ($this->getPerformUpdate() === true) {
                    $this->getUserHandler()->create($newUser);

                    $this->getVerboseFilter()->performCreation();
                    $this->getVerboseFilter()->incrementCreated();
                }
                // Report intentions
                else {
                    $this->getVerboseFilter()->planCreation();
                    $this->getVerboseFilter()->incrementCreated();
                }
            }
        }
        // Report intentions
        else {
            $this->getVerboseFilter()->skip();
            $this->getVerboseFilter()->incrementSkipped();
        }
    }


    /**
     * Determine if this person has a username
     *
     * @param  $currentUser
     * @return bool
     */
    protected function hasUsername($currentUser) {
        if (strlen(trim($currentUser['username']))) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user exists in Schoolbox
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExists($newUser) {
        return $this->getUserHandler()->getBySynergy($newUser['synergy_id']);
    }

    /**
     * Determine if a user exists in Schoolbox in a particular group
     *
     * @param $newUser
     * @return mixed
     */
    protected function userExistsBySynergyAndGroup($newUser) {
        $query = "
            SELECT
                    user.id as uid, synergy_id,
                    title, firstname, lastname, username,
                    local_email, alt_email,
                    userCampus.campus, year, house,
                    eportfolio_enabled,
                    groups.name AS gname, group_id
            FROM user
            LEFT JOIN groups ON user.group_id = groups.id
            LEFT JOIN userCampus ON user.id = userCampus.uid
            WHERE user.synergy_id = '{$newUser['synergy_id']}'
        ";

        // Only lock user to group if parent account
        if ($newUser['group_id'] == self::PARENT_GROUP_ID)
            $query .= " AND groups.id = '{$newUser['group_id']}'";


        $query .= " ORDER BY userCampus.defaultCampus DESC";

        return $this->getUserHandler()->db->getRow($query);
    }
}
