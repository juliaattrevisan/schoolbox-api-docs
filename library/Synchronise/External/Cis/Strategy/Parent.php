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
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\External\UserProfile;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelation;
use Schoolbox\External\UserRelationMapper;

class Synchronise_External_Cis_Strategy_Parent extends Synchronise_Base_Strategy_Parent
{
    use Synchronise_External_Cis_Strategy_BatchXmlTrait;

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
        try {
            $iterator   = new External_Isams_Batch_Reader_Parent($this->getXmlReader());
            $this->list = $iterator->getAccountInfo();
        } catch (Exception $e) {
            printf('Skipping parent sync: '.$e->getMessage());
        }
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
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Parent_SkipRelationships())
            ->addHydrator(new Synchronise_Base_Hydrator_BasicValue('webmail', false))
            ->addHydrator(new Synchronise_Base_Hydrator_BasicValue('eportfolio_enabled', false))
            ->addHydrator(new Synchronise_Base_Hydrator_BasicMapping('ID', 'synergy_id', 'P-%s'))
            // Parent campuses - let's just say they're all in campus "SS" for now?
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Parent_Campus(['SS', 'MS', 'HS']))
            ->addHydrator(new Synchronise_External_Cis_Hydrator_Title('StudentContactTitle'))
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

        // Update this user's profile
        try {
            $profile = $profileMapper->findById($newUser['id']);
        } catch (Exception $e) {
            $profile = $profileMapper->createModel($newUser['id']);
        }
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
        ;
        $profileMapper->save($profile);

        // Delete old relations
        $relationMapper->deleteByColumn('user_id', $newUser['id']);

        // Set up new relation for the parent
        $relations  = new \Schoolbox\Collection();
        $studentIds = array_map(
            function ($v) {
                return 'S-' . $v;
            },
            $externalUser['StudentContactStudentIds']
        );
        foreach ($userMapper->findByColumn('synergy_id', $studentIds) as $student) {
            /** @var User $student */
            /** @var UserRelation $relation */
            $relation = $relationMapper->createModel();
            $relations->add(
                $relation
                    ->setUser($newUser['id'])
                    ->setRelation($student)
                    ->setRelationType(UserRelation::RELATION_TYPE_PARENT)
            );
        }
        $relationMapper->save($relations);
    }
}
