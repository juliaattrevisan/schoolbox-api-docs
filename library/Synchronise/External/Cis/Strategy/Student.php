<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Student
 *
 * Update Schoolbox with Schoolbox data if there is something different
 * contained in Schoolbox database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;

class Synchronise_External_Cis_Strategy_Student extends Synchronise_Base_Strategy_Student
{
    use Synchronise_External_Cis_Strategy_BatchXmlTrait;

    protected $location = '';
    protected $columns  = array();
    protected $title    = "Update Schoolbox Student Details Synchronisation";

    /**
     * Creates a list from the XML reader.
     */
    public function generateList()
    {
        try {
            $iterator   = new External_Isams_Batch_Reader_Student($this->getXmlReader());
            $this->list = $iterator->getAccountInfo();
        } catch (Exception $e) {
            printf('Skipping student sync: '.$e->getMessage());
        }
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
            ->addHydrator(new Synchronise_Base_Hydrator_Student_UsernameFromEmail())
            // Secondary school only right now
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Student_Campus(['SS', 'MS', 'HS']))
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Student_Role())
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Student_Year())
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Title('StudentTitle'))
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
        /** @var UserProfile $profile */
        try {
            $profile = UserProfileMapper::getInstance()->findById($newUser['id']);
        } catch (Exception $e) {
            $profile = UserProfileMapper::getInstance()->createModel();
        }
        $profile
            ->setUserId($newUser['id'])
            ->setHouse($externalUser['StudentHouse'])
            ->setHomegroup($externalUser['StudentForm'])
            ->setLevel($newUser['year'])
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
