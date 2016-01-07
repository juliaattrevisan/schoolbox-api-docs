<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use House as HouseModel;
use HouseMapper;
use HouseResidential as HouseResidentialModel;
use HouseResidentialMapper;
use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;
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
     * @var UserMapper
     */
    protected $userMapper;

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
     * Does this Schoolbox have an external database?
     *
     * (The Schoolbox "external" connector is the only one that does not.)
     *
     * @return bool
     */
    public static function hasExternalDb()
    {
        // Sigh
        return \Config::get('external_db') !== 'schoolbox';
    }

    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        $format = [
            "Delete?"                      => 'Is this user to be deleted?',
            "Schoolbox User ID"            => 'The user\'s internal Schoolbox ID number',
            "Username"                     => 'The unique name the user uses to log in',
            "External ID"                  => 'The user\'s ID number in your external system',
            "Title"                        => '',
            "First Name"                   => '',
            "Surname"                      => '',
            "Role"                         => 'The Schoolbox role for this user',
            "Campus"                       => 'A comma-separated list of campus names for this user.',
            "Password"                     => 'The user\'s password. If left blank, it will not be updated',
            "Alt Email"                    => '',
            "Year"                         => '',
            "House"                        => '',
            "Residential House"            => '',
            "E-Portfolio?"                 => '',
            "Email Address From Username?" => '',
            "Use External Mail Client?"    => '',
            "Enable Webmail Tab?"          => '',
            "Superuser?"                   => '',
            "Account Enabled?"             => '',
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
        $this->userMapper = new UserMapper();

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
        $this->userIdentifiers[] = new User\Identifier\ExternalId($this->userMapper, $this->roleList);

        // Hydrators
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Username', 'setUsername');
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('External ID', 'setExternalId');
        $this->userHydrators[] = new User\Hydrator\Lookup('Role', 'setRole', $this->roleList);
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Password', 'setPassword');
        $this->userHydrators[] = new User\Hydrator\ToggleField('E-Portfolio?', 'setEportfolioEnable');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Email Address From Username?', 'setLocalEmail');
        // This one might have to be inverted
        $this->userHydrators[] = new User\Hydrator\ToggleField('Use External Mail Client?', 'setUseInternalMail');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Enable Webmail Tab?', 'setWebmail');
        // Not sure if superuser should be settable
        $this->userHydrators[] = new User\Hydrator\ToggleField('Superuser?', 'setSuperuser');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Account Enabled?', 'setEnabled');
        $this->userHydrators[] = new User\Hydrator\Campus('Campus', $this->campusList);
        $this->userHydrators[] = new User\Hydrator\Field('Title', 'setTitle');
        $this->userHydrators[] = new User\Hydrator\Field('First Name', 'setFirstname');
        $this->userHydrators[] = new User\Hydrator\Field('Surname', 'setLastname');
        $this->userHydrators[] = new User\Hydrator\Field('Alt Email', 'setAltEmail');
        $this->userHydrators[] = new User\Hydrator\Field('Year', 'setYear');
        $this->userHydrators[] = new User\Hydrator\Lookup('House', 'setSchoolHouse', $this->houseList);
        $this->userHydrators[] = new User\Hydrator\Lookup('Residential House', 'setResidentialHouse', $this->residentialHouseList);
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @return array|User\Result[]
     */
    protected function parse()
    {
        $result = [];
        foreach ($this->reader as $i => $row) {
            $this->log->info("Parsing row $i...", ['row' => $row]);

            // Step 1: identify user
            $user = $this->retrieveUser($row);
            if (!$user instanceof UserModel) {
                $this->log->warning('User could not be identified - skipping');
                continue;
            }

            $userResult = new User\Result();
            $userResult->setOriginalUser(clone $user);
            if ($user->getId()) {
                $this->log->info(sprintf('User found (ID: #%d)', $user->getId()));
            } else {
                $this->log->info('User not found - creating new user');
            }

            // Step 2: are they deleted?
            // Users aren't currently soft-deletable, so they stay deleted.
            if ($this->parseStringBoolean($row['Delete?'])) {
                $userResult->setIsUserDeleted(true);
            }

            // Step 3: update user based on fields
            $userResult->setUser($this->hydrateUser($row, $user));

//            // Step 4: update extended fields if using SB connector
//            if (!$this->hasExternalDb()) {
//                // update
//            }

            $result[] = $userResult;
        }
        return $result;
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
     * Retrieves a user to create or update.
     *
     * @param  array $row A row from the CSV import.
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
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        $result = $this->parse();
        foreach ($result as $userResult) {
            // Delete or save
            if ($userResult->isUserDeleted()) {
                $this->deleteUser($userResult);
            } else {
                $user = $userResult->getUser();
                if (empty($user->getId())) {
                    $this->createUser($userResult);
                } else {
                    $this->updateUser($userResult);
                }
            }
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
            $this->log->info(sprintf('Creating user "%s"', $user->formatName()), ['user' => $user]);
            try {
                if ($this->userMapper->save($user)) {
                    $this->log->info(sprintf('User created (ID: #%d)', $user->getId()));
                } else {
                    $this->log->error('Could not create user!');
                }
            } catch (\Exception $e) {
                $this->log->error('Could not create user!', ['error' => $e->getMessage()]);
            }
        } else {
            $this->log->info(sprintf('Pretending to create user "%s"', $user->formatName()), ['user' => $user]);
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
                if ($this->userMapper->save($user)) {
                    $this->log->info(sprintf('User updated (ID: #%d)', $user->getId()));
                } else {
                    $this->log->error('Could not update user!');
                }
            } catch (\Exception $e) {
                $this->log->error('Could not update user!', ['error' => $e->getMessage()]);
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
            $this->log->info(sprintf('Deleting user "%s"', $user->formatName()), ['user' => $user]);
            try {
                if ($this->userMapper->delete($user)) {
                    $this->log->info('User deleted');
                } else {
                    $this->log->error('Could not delete user!');
                }
            } catch (\Exception $e) {
                $this->log->error('Could not delete user!', ['error' => $e->getMessage()]);
            }
        } else {
            $this->log->info(sprintf('Pretending to delete user "%s"', $user->formatName()), ['user' => $user]);
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
