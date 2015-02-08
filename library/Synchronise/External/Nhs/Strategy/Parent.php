<?php
/**
 * Synchronise_Schoolbox_Nhs_Strategy_Parent
 *
 * Update Schoolbox with data set from a CSV file.
 *
 * Notes for the Northcote sync:
 * * There appears to only be a single account per couple, there doesn't appear
 *   to be separate spouse accounts
 * * The Schoolbox connector returns nothing for parent details anyway, so they
 *   should be added into the student's user profile
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelation;
use Schoolbox\External\UserRelationMapper;

class Synchronise_External_Nhs_Strategy_Parent extends Synchronise_Base_Strategy_Parent {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Parent Details Synchronisation";
    protected $relation_list = array();

    /**
     * Sets up synchronisation with parameters passed from the definition.
     *
     * @param string $location Where the CSV for import is located
     * @param array  $columns  The columns to import
     */
    public function __construct($location, $columns) {
        parent::__construct();
        $this->location = $location;
        $this->columns  = $columns;
    }

    /**
     * Creates a list from a CSV file instead of from a DB connection
     */
    public function generateList() {
        $this->list = array();

        // Load parent list
        $reader = new Alaress\Csv\Reader($this->location);
        $reader->setColumnList($this->columns);
        foreach ($reader as $row) {
            $row['StudentContactSurname']   = '';
            $row['StudentContactPreferred'] = '';
            $row['StudentContactTitle']     = '';
            if (!empty($row['StudentContactName'])) {
                list($row['StudentContactSurname'], $row['StudentContactPreferred']) = array_map(
                    'trim', explode(',', $row['StudentContactName'])
                );
            }
            if (!empty($row['StudentContactSpouseName'])) {
                list($row['StudentContactSpouseSurname'], $row['StudentContactSpousePreferred']) = array_map(
                    'trim', explode(',', $row['StudentContactSpouseName'])
                );
            }
            $this->list[] = $row;
        }
        unset($reader);

        // Load relations
        $reader = new Alaress\Csv\Reader(Config::get('external_sync_relation_csv'));
        foreach ($reader as $row) {
            list($child, $parent, $type) = $row;
            // Relation types:
            // 0: child -> parent
            // 1: child -> separated parent? (don't associate with child?)
            $this->relation_list[$parent][$child] = $type;
        }
    }

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter = new Synchronise_External_Nhs_Verbose();
    }

    /**
     * Set parent's role to 3. Why didn't we pick up on this before?
     *
     * @param array $currentUser
     *
     * @return int
     */
    public function getGroupIDFromUser($currentUser)
    {
        return 3;
    }

    /**
     * For a user which contains both contact and spouse: return nothing for
     * first name, and "$surname Family" for surname.
     *
     * For a user which doesn't: fall back to standard implementation.
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getFirstNameFromUser($currentUser) {
        if (!empty($currentUser['StudentContactSpouseName'])) {
            return '';
        }
        return parent::getFirstNameFromUser($currentUser);
    }

    /**
     * @see $this->getFirstNameFromUser()
     *
     * @param  array  $currentUser
     * @return string
     */
    public function getLastNameFromUser($currentUser) {
        if (!empty($currentUser['StudentContactSpouseName'])) {
            return $currentUser['StudentContactSurname'] . ' Family';
        }
        return $currentUser['StudentContactSurname'];
    }

    /**
     * Create parent accounts, user relations and user profiles.
     */
    public function createIfExists() {

        // Create parent accounts
        parent::createIfExists();

        // Fill in user profile and relations
        foreach ($this->list as $currentUser) {
            $parent_details = $this->setupUserDetails($currentUser);
            $this->verbose_filter->setNewUser($parent_details);

            // Clear relations for this parent.
            /** @var User $parentAccount */
            $parentAccount = null;
            try {
                $parentAccount = UserMapper::getInstance()->findOneByColumn(
                    'synergy_id', $parent_details['synergy_id']
                );
            }
            catch (Exception $e) {}
            if ($parentAccount instanceof User) {
                if ($this->perform_update) {
                    UserRelationMapper::getInstance()->deleteForUser($parentAccount);
                    $this->verbose_filter->outputMessage(
                        sprintf('Clearing relations for parent "%s"', $parent_details['synergy_id'])
                    );
                }
                else {
                    $this->verbose_filter->outputMessage(
                        sprintf('Planning to clear relations for parent "%s"', $parent_details['synergy_id'])
                    );
                }
            }
            else {
                // Nothing found, do something else.
                $this->verbose_filter->outputSkipMessage(
                    sprintf('relations for parent "%s", because user was not found', $parent_details['synergy_id'])
                );
            }


            // Get this parent's children, and link to each of those children.
            if (!empty($this->relation_list[$parent_details['synergy_id']])) {
                foreach ($this->relation_list[$parent_details['synergy_id']] as $child_id => $type) {

                    $message = sprintf('info for student "%s"', $child_id);

                    // Setup user relations
                    if ($parentAccount instanceof User) {
                        $childAccount = null;
                        try {
                            $childAccount = UserMapper::getInstance()->findOneByColumn(
                                'synergy_id', $child_id
                            );
                        }
                        catch (Exception $e) {}
                        if ($childAccount instanceof User) {
                            // SB-4936: whether parent is primary guardian or not
                            // no longer matters
//                            if (intval($type) !== 0) {
//                                $this->verbose_filter->outputSkipMessage(
//                                    sprintf(
//                                        'parent relation for parent "%s" to child "%s", because user is not primary guardian',
//                                        $parentAccount->getExternalId(),
//                                        $childAccount->getExternalId()
//                                    )
//                                );
//                            }
//                            else
                            if ($this->perform_update) {
                                $userRelation = new UserRelation();
                                $userRelation
                                    ->setUser($parentAccount)
                                    ->setRelation($childAccount)
                                    ->setRelationType(UserRelation::RELATION_TYPE_PARENT);
                                UserRelationMapper::getInstance()->save($userRelation);
                                $this->verbose_filter->outputPerformCreateMessage(
                                    sprintf(
                                        'parent relation for parent "%s" to child "%s"',
                                        $parentAccount->getExternalId(),
                                        $childAccount->getExternalId()
                                    )
                                );
                            }
                            else {
                                $this->verbose_filter->outputPlanCreateMessage(
                                    sprintf(
                                        'parent relation for parent "%s" to child "%s"',
                                        $parentAccount->getExternalId(),
                                        $childAccount->getExternalId()
                                    )
                                );
                            }
                        }
                        else {
                            // Nothing found, do something else.
                            $this->verbose_filter->outputMessage(
                                'parent '.$message.', because child\'s account was not found'
                            );
                            $this->verbose_filter->incrementSkipped();
                        }
                    }

                    // Now save the profile.
                    $profile = null;
                    try {
                        $profile = UserProfileMapper::getInstance()->findByExternalId($child_id);
                    }
                    catch (Exception $e) {
                        // Nothing found
                    }

                    if (!($profile instanceof UserProfile)) {
                        $this->verbose_filter->outputSkipMessage('parent '.$message.', because profile was not found');
                        $this->verbose_filter->incrementSkipped();
                    }
                    // SB-4936
//                    elseif (intval($type) !== 0) {
//                        $this->verbose_filter->outputSkipMessage('parent '.$message.', because user is not primary guardian');
//                        $this->verbose_filter->incrementSkipped();
//                    }
                    else {
                        $profile
                            ->setGuardian1Name($currentUser['StudentContactName'])
                            ->setGuardian1Email($currentUser['StudentContactDefaultEmail'])
                            ->setGuardian1Address($currentUser['StudentContactAddress'])
                            ->setGuardian1Suburb($currentUser['StudentContactSuburb'])
                            ->setGuardian1Postcode($currentUser['StudentContactPostcode'])
                            ->setGuardian1HomePhone($currentUser['StudentContactPhone'])
                            ->setGuardian1MobilePhone($currentUser['StudentContactEmergencyPhone'])
                            ->setGuardian1WorkPhone($currentUser['StudentContactEmergencyPhone'])
                        ;
                        // Don't set guardian 2 unless their name is set here
                        if (!empty($currentUser['StudentContactSpouseName'])) {
                            $profile
                                ->setGuardian2Name($currentUser['StudentContactSpouseName'])
                                ->setGuardian2Email($currentUser['StudentContactSpouseDefaultEmail'])
                                ->setGuardian2Address($currentUser['StudentContactAddress'])
                                ->setGuardian2Suburb($currentUser['StudentContactSuburb'])
                                ->setGuardian2Postcode($currentUser['StudentContactPostcode'])
                                ->setGuardian2HomePhone($currentUser['StudentContactSpouseWorkPhone'])
                                ->setGuardian2MobilePhone($currentUser['StudentContactEmergencyPhone'])
                                ->setGuardian2WorkPhone($currentUser['StudentContactEmergencyPhone'])
                            ;
                            $message = 'parent and spouse '.$message;
                        }
                        else {
                            $message = 'parent '.$message;
                        }
                        if ($this->perform_update) {
                            UserProfileMapper::getInstance()->save($profile);
                            $this->verbose_filter->outputPerformUpdateMessage($message);
                        }
                        else {
                            $this->verbose_filter->outputPlanUpdateMessage($message);
                        }
                        $this->verbose_filter->incrementUpdated();
                    }
                }
            }
        }
        $this->verbose_filter->summary();
    }

}
