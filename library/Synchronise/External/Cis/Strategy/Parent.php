<?php
/**
 * Synchronise_Schoolbox_Cis_Strategy_Parent
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

class Synchronise_External_Cis_Strategy_Parent extends Synchronise_Base_Strategy_Parent {

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
    /**
     * Creates a list from the XML reader.
     */
    public function generateList()
    {
        $reader = new XMLReader();
        $reader->open(SITE_root.'/students.xml');
        $iterator   = new External_Isams_Batch_Reader_Parent($reader);
        $this->list = $iterator->getAccountInfo();
    }

    /**
     * Setup verbose output
     */
    protected function setup()
    {
        $this->verbose_filter = new Synchronise_External_Cis_Verbose();
        $this
            ->addHydrator(new Synchronise_Base_Hydrator_Default())
            ->addHydrator(new Synchronise_Base_Hydrator_Parent())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping('ID', 'external_id', 'P-%'))
            ->addHydrator(new Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate())
            ->addHydrator(new Synchronise_Base_Hydrator_Clear())
//            ->addHydrator(new Synchronise_Base_Hydrator_AllowUpdateOnly())
            // no separate spouse entries.
            ->addSpouseHydrator(new Synchronise_Base_Hydrator_Clear())
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
        // Set up profile and relations
        $userMapper     = UserMapper::getInstance();
        $relationMapper = UserRelationMapper::getInstance();
        $profileMapper  = UserProfileMapper::getInstance();

        // Delete old relations
        $relationMapper->deleteByColumn('user_id', $newUser['id']);

        // Set up new relation for the parent
        $relations = new \Schoolbox\Collection();
        $profiles  = new \Schoolbox\Collection();
        foreach ($userMapper->findByColumn('synergy_id', $externalUser['StudentContactStudentIds']) as $student) {
            /** @var User $student */
            /** @var UserRelation $relation */
            $relation = $relationMapper->createModel();
            $relations->add(
                $relation
                    ->setUser($newUser['id'])
                    ->setRelation($student)
                    ->setRelationType(UserRelation::RELATION_TYPE_PARENT)
            );

            // Update this student's profile
            // Profile exists? update it, otherwise leave alone
            try {
                $profile = $profileMapper->findById($student->getId());
            } catch (Exception $e) {
                continue;
            }

            // Set guardian 1 if it isn't set yet, guardian 2 if it is,
            // skip if both are set
            $name    = trim($externalUser['StudentContactPreferred'].' '.$externalUser['StudentContactSurname']);
            $address = implode(
                ', ',
                array_filter(
                    array_map(
                        'trim',
                        [
                            $externalUser['StudentContactAddress1'],
                            $externalUser['StudentContactAddress2'],
                            $externalUser['StudentContactAddress3'],
                        ]
                    )
                )
            );
            if (strlen($profile->getGuardian1Name()) === 0) {
                $profile
                    ->setGuardian1Name($name)
                    ->setGuardian1HomePhone($externalUser['StudentContactPhoneActual'])
                    ->setGuardian1WorkPhone($externalUser['StudentContactOccupPhone'])
                    ->setGuardian1MobilePhone($externalUser['StudentContactMobilePhone'])
                    ->setGuardian1Address($address)
                    ->setGuardian1Suburb($externalUser['StudentContactSuburb'])
                    ->setGuardian1Postcode($externalUser['StudentContactPostCode'])
                    ->setGuardian1Email($externalUser['StudentContactDefaultEmail'])
                    ->setGuardian1MailAddress('')
                    ->setGuardian2Name('')
                    ->setGuardian2HomePhone('')
                    ->setGuardian2WorkPhone('')
                    ->setGuardian2MobilePhone('')
                    ->setGuardian2Address('')
                    ->setGuardian2Suburb('')
                    ->setGuardian2Postcode('')
                    ->setGuardian2Email('')
                    ->setGuardian2MailAddress('')
                ;
                $profiles->add($profile);
            } elseif (strlen($profile->getGuardian2Name()) === 0) {
                $profile
                    ->setGuardian2Name($name)
                    ->setGuardian2HomePhone($externalUser['StudentContactPhoneActual'])
                    ->setGuardian2WorkPhone($externalUser['StudentContactOccupPhone'])
                    ->setGuardian2MobilePhone($externalUser['StudentContactMobilePhone'])
                    ->setGuardian2Address($address)
                    ->setGuardian2Suburb($externalUser['StudentContactSuburb'])
                    ->setGuardian2Postcode($externalUser['StudentContactPostCode'])
                    ->setGuardian2Email($externalUser['StudentContactDefaultEmail'])
                    ->setGuardian2MailAddress('')
                ;
                $profiles->add($profile);
            }
        }
        $relationMapper->save($relations);
        $profileMapper->save($profiles);
    }
}
