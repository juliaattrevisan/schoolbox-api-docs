<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Alaress\Password\Service;
use House as HouseModel;
use HouseMapper;
use HouseResidential as HouseResidentialModel;
use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\UserProfile as UserProfileModel;
use Schoolbox\External\UserProfileMapper;
use Schoolbox\External\UserRelation as UserRelationModel;
use Schoolbox\External\UserRelationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role as RoleModel;
use Schoolbox\RoleMapper;
use Schoolbox\User as UserModel;
use Schoolbox\UserMapper;

/**
 * Importer for users into the Schoolbox connector.
 *
 * Saves into the following tables:
 * * user
 * * userCampus
 * * superusers
 *
 * Also saves into the following tables, if using the Schoolbox connector:
 * * user_relation (guardian-child relations)
 * * user_profile (supplementary user information)
 *
 * @package Schoolbox\External\Importer
 */
class User extends AbstractImporter
{
    /**
     * @var Reader
     */
    protected $csvReader;

    /**
     * @var Service
     */
    protected $passwordService;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var UserProfileMapper
     */
    protected $userProfileMapper;

    /**
     * @var UserRelationMapper
     */
    protected $userRelationMapper;

    /**
     * @var array|RoleModel[]
     */
    protected $roleList = [];

    /**
     * @var array|CampusModel[]
     */
    protected $campusList = [];

    /**
     * @var array|HouseModel[]
     */
    protected $houseList = [];

    /**
     * @var array|HouseResidentialModel[]
     */
    protected $residentialHouseList = [];

    /**
     * A list of user identifier classes - each one will in turn attempt to
     * retrieve a UserModel from the row.
     *
     * @var array|User\Identifier\IdentifierInterface[]
     */
    protected $userIdentifiers = [];

    /**
     * A list of user hydrator classes - each one will in turn attempt to
     * update a UserModel based on the values in the row.
     *
     * @var array|User\Hydrator\HydratorInterface[]
     */
    protected $userHydrators = [];

    /**
     * Same as $userHydrators, but for the user profile table.
     *
     * @var array|UserProfile\Hydrator\HydratorInterface[]
     */
    protected $userProfileHydrators = [];

    /**
     * Same as $userHydrators, but for the user relation table.
     *
     * @var array|UserRelation\Hydrator\HydratorInterface[]
     */
    protected $userRelationHydrators = [];

    /**
     * Does this Schoolbox have an external database?
     *
     * (The Schoolbox "external" connector is the only one that does not.)
     *
     * @return bool
     */
    public static function hasExternalDb()
    {
        // Sigh
        return \Config::get('external_type') !== 'schoolbox';
    }

    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        $format = [
            "Delete?"                              => 'Is this user to be deleted?',
            "Schoolbox User ID"                    => 'The user\'s internal Schoolbox ID number',
            "Username"                             => 'The unique name the user uses to log in',
            "External ID"                          => 'The user\'s ID number in your external system',
            "Title"                                => '',
            "First Name"                           => '',
            "Surname"                              => '',
            "Position Title"                       => 'The user\'s position title (e.g. Admissions Manager)',
            "Role"                                 => 'The Schoolbox role for this user',
            "Campus"                               => 'A comma-separated list of campus names for this user.',
            "Password"                             => 'The user\'s password. If left blank, it will not be updated',
            "Alt Email"                            => '',
            "Year"                                 => '',
            "House"                                => '',
            "Residential House"                    => '',
            "E-Portfolio?"                         => '',
            "Hide Contact Details?"                => '',
            "Hide Timetable?"                      => '',
            "Email Address From Username?"         => '',
            "Use External Mail Client?"            => '',
            "Enable Webmail Tab?"                  => '',
            'Display Graph on Student Grade Card?' => '',
            "Superuser?"                           => '',
            "Account Enabled?"                     => '',
        ];
        if (!self::hasExternalDb()) {
            $format += [
                // user_relation
                "Child External IDs"           => '',
                // user_profile
                "Date of Birth"                => '',
                // Year Level should always be the same as Year
//                "Year Level"                   => '',
                "Guardian 1 Name"              => '',
                "Guardian 1 Email"             => '',
                "Guardian 1 Home Phone"        => '',
                "Guardian 1 Mobile Phone"      => '',
                "Guardian 1 Work Phone"        => '',
                "Guardian 1 Address"           => '',
                "Guardian 1 Suburb"            => '',
                "Guardian 1 Postcode"          => '',
                "Guardian 2 Name"              => '',
                "Guardian 2 Email"             => '',
                "Guardian 2 Home Phone"        => '',
                "Guardian 2 Mobile Phone"      => '',
                "Guardian 2 Work Phone"        => '',
                "Guardian 2 Address"           => '',
                "Guardian 2 Suburb"            => '',
                "Guardian 2 Postcode"          => '',
            ];
        }
        return $format;
    }

    /**
     * Custom setup function
     */
    protected function setup()
    {
        $this->passwordService    = new Service();
        $this->userMapper         = new UserMapper();
        $this->userProfileMapper  = new UserProfileMapper();
        $this->userRelationMapper = new UserRelationMapper();

        // Role list
        foreach (RoleMapper::getInstance()->findAll() as $role) {
            /** @var RoleModel $role */
            $this->roleList[$role->getName()] = $role;
        }

        // Campus list
        foreach (CampusMapper::getInstance()->findAll() as $campus) {
            /** @var CampusModel $campus */
            $this->campusList[$campus->getName()] = $campus;
        }

        // House list
        foreach (HouseMapper::getInstance()->findAll() as $house) {
            /** @var HouseModel $house */
            $this->houseList[$house->getHouse()] = $house;
        }

        // Residential House list
        foreach (\HouseResidentialMapper::getInstance()->findAll() as $boardingHouse) {
            /** @var HouseResidentialModel $boardingHouse */
            $this->residentialHouseList[$boardingHouse->getHouse()] = $boardingHouse;
        }

        // Identifiers
        $this->userIdentifiers[] = new User\Identifier\UserId($this->userMapper);
        $this->userIdentifiers[] = new User\Identifier\Username($this->userMapper);
        $this->userIdentifiers[]  = new User\Identifier\ExternalId($this->userMapper, $this->roleList);

        // Hydrators
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Username', 'setUsername', true);
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('External ID', 'setExternalId', false);
        $this->userHydrators[] = new User\Hydrator\Lookup('Role', 'setRole', $this->roleList);
        $this->userHydrators[] = new User\Hydrator\Password('Password', 'setPassword', $this->passwordService);
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Password', 'setRetrievablePassword', false);
        $this->userHydrators[] = new User\Hydrator\ToggleField('E-Portfolio?', 'setEportfolioEnable');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Hide Contact Details?', 'setHideContacts');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Hide Timetable?', 'setHideTimetable');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Email Address From Username?', 'setLocalEmail');
        // This one might have to be inverted
        $this->userHydrators[] = new User\Hydrator\ToggleField('Use External Mail Client?', 'setUseInternalMail');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Enable Webmail Tab?', 'setWebmail');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Display Graph on Student Grade Card?', 'setDisplayGradeGraph');
        // Not sure if superuser should be settable
        $this->userHydrators[] = new User\Hydrator\ToggleField('Superuser?', 'setSuperuser');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Account Enabled?', 'setEnabled');
        $this->userHydrators[] = new User\Hydrator\Campus('Campus', $this->campusList);
        $this->userHydrators[] = new User\Hydrator\Field('Title', 'setTitle', true);
        $this->userHydrators[] = new User\Hydrator\Field('First Name', 'setFirstname', true);
        $this->userHydrators[] = new User\Hydrator\Field('Surname', 'setLastname', true);
        $this->userHydrators[] = new User\Hydrator\Field('Position Title', 'setPositionTitle', true);
        $this->userHydrators[] = new User\Hydrator\Field('Alt Email', 'setAltEmail', true);
        $this->userHydrators[] = new User\Hydrator\Field('Year', 'setYear', true);
        $this->userHydrators[] = new User\Hydrator\Lookup('House', 'setSchoolHouse', $this->houseList);
        $this->userHydrators[] = new User\Hydrator\Lookup('Residential House', 'setResidentialHouse', $this->residentialHouseList);

        // User Profile hydrators
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Date('Date of Birth', 'setDob');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field('Year', 'setLevel');
        // Join these fields together
        $this->userProfileHydrators[] = new UserProfile\Hydrator\MultiField(['Title', 'First Name', 'Surname'], 'setGuardian1Name');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Alt Email", 'setGuardian1Email');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Home Phone", 'setGuardian1HomePhone');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Mobile Phone", 'setGuardian1MobilePhone');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Work Phone", 'setGuardian1WorkPhone');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Address", 'setGuardian1Address');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Suburb", 'setGuardian1Suburb');
        $this->userProfileHydrators[] = new UserProfile\Hydrator\Field("Postcode", 'setGuardian1Postcode');

        // Parent-child relation hydrator
        $this->userRelationHydrators[] = new UserRelation\Hydrator\ExternalId("Child External IDs", $this->userMapper);
    }

    /**
     * Performs a sanity check on this user
     *
     * Throws validation exceptions if it isn't.
     *
     * @param User\Result $userResult
     * @throws \UnexpectedValueException if a value isn't what it should be
     */
    protected function sanityCheck(User\Result $userResult)
    {
        $userId = $userResult->getUser()->getExternalId();
        foreach ($userResult->getChildren() as $child) {
            if ($userId === $child->getExternalId()) {
                throw new \UnexpectedValueException("A child cannot be their own parent. External ID: $userId");
            }
        }
    }

    /**
     * Retrieves a user to create or update.
     *
     * @param  array $row A row from the CSV import.
     * @return UserModel or null if no user was retrievable from $row
     */
    protected function retrieveUser($row)
    {
        foreach ($this->userIdentifiers as $identifier) {
            if ($identifier->accept($row)) {
                try {
                    return $identifier->retrieve($row);
                } catch (\Exception $e) {
                    $this->log->warning($e->getMessage());
                    return null;
                }
            }
        }
        return null;
    }

    /**
     * Updates a user based on the user hydrators.
     *
     * @param  array     $row  A row from the CSV import.
     * @param  UserModel $user The user object to update
     * @return UserModel An updated user object
     */
    protected function hydrateUser($row, UserModel $user)
    {
        foreach ($this->userHydrators as $hydrator) {
            $user = $hydrator->hydrate($row, $user);
        }
        return $user;
    }

    /**
     * Retrieves a user profile to create or update.
     *
     * @param  UserModel $user
     * @return UserProfileModel
     */
    protected function retrieveUserProfile(UserModel $user)
    {
        $userId = $user->getId();
        if ($userId) {
            try {
                return $this->userProfileMapper->findById($userId);
            } catch (MapperException $e) {
                // noop - fall through to returning empty model
            }
        }
        return $this->userProfileMapper->createModel();
    }

    /**
     * Retrieves users of whom this user is a guardian.
     *
     * @param  UserModel $user
     * @return UserModel[]
     */
    protected function retrieveUserChildren(UserModel $user)
    {
        $userId   = $user->getId();
        $childIds = [];
        if ($userId) {
            foreach ($this->userRelationMapper->findByColumn('user_id', $userId) as $userRelation) {
                /** @var UserRelationModel $userRelation */
                // Fully populate the user fields
                $childIds[] = $userRelation->getRelation()->getId();
            }
            return $this->userMapper->findByColumn('id', $childIds);
        }
        return new Collection();
    }

    /**
     * Updates a user profile based on the user profile hydrators.
     *
     * @param  array            $row         A row from the CSV import.
     * @param  UserProfileModel $userProfile The user object to update
     * @return UserProfileModel An updated user object
     */
    protected function hydrateUserProfile($row, UserProfileModel $userProfile)
    {
        foreach ($this->userProfileHydrators as $hydrator) {
            try {
                $userProfile = $hydrator->hydrate($row, $userProfile);
            }
            catch (\UnexpectedValueException $e) {
                $this->log->warning($e->getMessage());
                continue;
            }
        }
        return $userProfile;
    }


    /**
     * Updates a user profile based on the user profile hydrators.
     *
     * @param  array       $row      A row from the CSV import.
     * @param  UserModel[] $children The child list to update
     * @return UserModel[] An updated user object
     */
    protected function hydrateChildren($row, $children)
    {
        foreach ($this->userRelationHydrators as $hydrator) {
            $children = $hydrator->hydrate($row, $children);
        }
        return $children;
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        foreach ($this->reader as $i => $row) {

            // Step 1: identify user
            $user = $this->retrieveUser($row);
            if (!$user instanceof UserModel) {
                $this->log->warning('User could not be identified - skipping');
                continue;
            }

            $userResult = new User\Result();
            $userResult->setOriginalUser(clone $user);

            // Step 2: are they deleted?
            // Users aren't currently soft-deletable, so they stay deleted.
            if ($this->parseStringBoolean($row['Delete?'])) {
                $userResult->setIsUserDeleted(true);
            }

            // Step 3: update user based on fields
            $userResult->setUser($this->hydrateUser($row, $user));

            // Step 4: update extended fields if using SB connector
            if (!$this->hasExternalDb()) {
                // User profile
                $userProfile = $this->retrieveUserProfile($user);
                $userResult->setOriginalUserProfile(clone $userProfile);
                $userResult->setUserProfile($this->hydrateUserProfile($row, $userProfile));

                // User's children (identified by external ID)
                $userChildren = $this->retrieveUserChildren($user);
                $userResult->setOriginalChildren(clone $userChildren);
                $userResult->setChildren($this->hydrateChildren($row, $userChildren));
            }

            // Step 5: sanity check - is data logically valid?
            try{
                $this->sanityCheck($userResult);
            } catch (\UnexpectedValueException $e){
                $this->log->warning(sprintf("%s - skipping",$e->getMessage()),['row' => $row]);
                continue;
            }


            // Step 6: save user
            if ($userResult->isUserDeleted()) {
                $this->deleteUser($userResult);
            } else {
                $user = $userResult->getUser();
                if (empty($user->getId())) {
                    $this->createUser($userResult);
                } else {
                    $this->updateUser($userResult);
                }
                if (!$this->hasExternalDb()) {
                    $this->updateUserProfile($userResult);
                }
            }


            // Step 6: save user relations (if using SB connector)
            if (!$this->hasExternalDb()) {
                $this->updateChildren($userResult);
            }

            unset($userResult);
            unset($user);
        }
    }

    /**
     * Creates a user record.
     *
     * @param User\Result $userResult
     */
    protected function createUser(User\Result $userResult)
    {
        $user = $userResult->getUser();
        if ($this->performUpdate) {
            $this->log->info(sprintf('Creating user "%s"', $user->formatName()));
            try {
                if (!$this->userMapper->save($user)) {
                    $this->log->error('Could not create user!');
                }
            } catch (\Exception $e) {
                $this->log->error('User not created: '.$e->getMessage() );
            }
        } else {
            $this->log->info(sprintf('Pretending to create user "%s"', $user->formatName()));
        }
    }

    /**
     * Updates a user record.
     *
     * @param User\Result $userResult
     */
    protected function updateUser(User\Result $userResult)
    {
        $user = $userResult->getUser();
        $diff = $this->userMapper->diff($userResult->getOriginalUser(), $user);
        if ($this->performUpdate) {
            $this->log->info(sprintf('Updating user "%s"', $user->formatName()), ['diff' => $diff]);
            try {
                if (!$this->userMapper->save($user)) {
                    $this->log->error('Could not update user!');
                }
            } catch (\Exception $e) {
                $this->log->error('User not created: '.$e->getMessage() );
            }
        } else {
            $this->log->info(sprintf('Pretending to update user "%s"', $user->formatName()), ['diff' => $diff]);
        }
    }

    /**
     * Deletes this user record.
     *
     * @param User\Result $userResult
     */
    protected function deleteUser(User\Result $userResult)
    {
        $user = $userResult->getUser();
        if (!$user->getId()) {
            $this->log->warning(
                sprintf(
                    'Cannot delete user "%s" - they don\'t exist yet',
                    $user->formatName()
                ),
                ['user' => $user]
            );
        }
        if ($this->performUpdate) {
            $this->log->info(sprintf('Deleting user "%s"', $user->formatName()));
            try {
                if (!$this->userMapper->delete($user)) {
                    $this->log->error('Could not delete user!');
                }
            } catch (\Exception $e) {
                $this->log->error('Could not delete user: '.$e->getMessage() );
            }
        } else {
            $this->log->info(sprintf('Pretending to delete user "%s"', $user->formatName()));
        }
    }

    /**
     * Updates a user profile record.
     *
     * @param User\Result $userResult
     */
    protected function updateUserProfile(User\Result $userResult)
    {
        $user        = $userResult->getUser();
        $userProfile = $userResult->getUserProfile();
        $diff        = $this->userProfileMapper->diff($userResult->getOriginalUserProfile(), $userProfile);
        if ($this->performUpdate) {
            $this->log->info(sprintf('Updating user profile for "%s"', $user->formatName()), ['diff' => $diff]);
            try {
                $userProfile->setUserId($user->getId());
                if (!$this->userProfileMapper->save($userProfile)) {
                    $this->log->error('Could not update user profile!');
                }
            } catch (\Exception $e) {
                $this->log->error('Could not update user profile: '. $e->getMessage() );
            }
        } else {
            $this->log->info(sprintf('Pretending to update user profile for "%s"', $user->formatName()), ['diff' => $diff]);
        }
    }

    /**
     * Updates a user profile record.
     *
     * @param User\Result $userResult
     */
    protected function updateChildren(User\Result $userResult)
    {
        $user             = $userResult->getUser();
        $originalChildren = $userResult->getOriginalChildren();

        // Load full child records.
        // This must be done here, as child users aren't guaranteed to exist
        // until users have been saved.
        $newExternalIds = [];
        foreach ($userResult->getChildren() as $newChild) {
            $newExternalIds[] = $newChild->getExternalId();
        }
        $newChildren = $this->userMapper->findByColumn('synergy_id', $newExternalIds);

        $context = [
            'old' => $originalChildren,
            'new' => $newChildren,
        ];

        // Diff the list
        if ($this->performUpdate) {
            $this->log->info(sprintf('Updating children for "%s"', $user->formatName()), $context);
            try {
                // Wipe and save the new users
                $this->userRelationMapper->deleteByColumn('user_id', $user->getId());
                $newRelations = [];
                foreach ($newChildren as $newChild) {
                    $newRelation = new UserRelationModel();
                    $newRelation
                        ->setUser($user)
                        ->setRelation($newChild)
                        ->setRelationType(UserRelationModel::RELATION_TYPE_PARENT)
                    ;
                    $newRelations[] = $newRelation;
                }
                $this->userRelationMapper->save($newRelations);
            } catch (\Exception $e) {
                $this->log->error('Could not update children: '.$e->getMessage());
            }
        } else {
            $this->log->info(sprintf('Pretending to update children for "%s"', $user->formatName()), $context);
        }
    }


    /**
     * Parses a string from a CSV field into a boolean value.
     *
     * @param string $value
     * @return bool or null if it isn't understandable
     */
    protected function parseStringBoolean($value)
    {
        switch (strtolower(trim($value))) {
            case 'y':
            case 'on':
            case 'true':
            case 1:
                return true;
            case 'n':
            case 'off':
            case 'false':
            case 0:
                return false;
            default:
                return null;
        }
    }
}
