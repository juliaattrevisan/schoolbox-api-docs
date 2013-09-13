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

use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;

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
     * Doesn't do anything, but always returns 1 so that executePostCreateUpdate
     * runs.
     *
     * @param  array $newUser
     * @return int
     */
    protected function executeCreateUpdate($newUser) {
        return 1;
    }


    public function createIfExists() {
        $this->verbose_filter->intro();

        foreach ($this->list as $currentUser) {
            $parent_details = $this->setupUserDetails($currentUser);
            $this->verbose_filter->setNewUser($parent_details);

            // Get this parent's children, and link to each of those children.
            if (!empty($this->relation_list[$parent_details['synergy_id']])) {
                foreach ($this->relation_list[$parent_details['synergy_id']] as $child_id => $type) {
                    $profile = null;
                    try {
                        $profile = UserProfileMapper::getInstance()->findByExternalId($child_id);
                    }
                    catch (Exception $e) {
                        // Nothing found
                    }
                    if ($profile instanceof UserProfile && $type === '0') {
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
                        }
                        if ($this->perform_update) {
                            UserProfileMapper::getInstance()->save($profile);
                        }
                        else {
                            $this->verbose_filter->planUpdate();
                        }
                        $this->verbose_filter->incrementUpdated();
                    }
                    else {
                        $this->verbose_filter->skip();
                        $this->verbose_filter->incrementSkipped();
                    }

                }
            }
        }
    }

}
