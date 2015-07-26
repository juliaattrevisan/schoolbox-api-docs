<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Student
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;

class Synchronise_External_Cis_Strategy_Student extends Synchronise_Base_Strategy_Student {

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Student Details Synchronisation";

    /**
     * Creates a list from the XML reader.
     */
    public function generateList()
    {
        $reader = new XMLReader();
        $reader->open(SITE_root.'/students.xml');
        $iterator   = new External_Isams_Batch_Reader_Student($reader);
        $this->list = $iterator->getAccountInfo();
    }

    /**
     * {@inheritdoc}
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_External_Cis_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Student())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping('ID', 'synergy_id', 'S-%s'))
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
        ;
    }

    /**
     * Sticks stuff into the Schoolbox connector.
     *
     * @param array $newUser
     * @param array $externalUser
     */
    protected function executePostCreateUpdate($newUser, $externalUser)
    {
        try {
            $profile = UserProfileMapper::getInstance()->findById($newUser['id']);
        } catch (Exception $e) {
            $profile = UserProfileMapper::getInstance()->createModel();
        }
        // Clear the user contact details - restore in parent sync
        $profile
            ->setUserId($newUser['id'])
            ->setHouse($externalUser['StudentHouse'])
            ->setLevel($externalUser['StudentYearLevel'])
            ->setDob(new Alaress_Date_Format($externalUser['StudentBirthDate'], 'Y-m-d'))
            ->setBirthDate($externalUser['StudentBirthDate'])
            ->setHomegroup($externalUser['StudentClassGroup'])
            ->setGivenName($externalUser['StudentPreferred'])
            ->setLastName($externalUser['StudentSurname'])
            ->setGender($externalUser['StudentGender'])
            ->setEmail($externalUser['StudentEmail'])
        ;
        UserProfileMapper::getInstance()->save($profile);
    }

}
