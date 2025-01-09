<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Password\Service;
use House as HouseModel;
use HouseMapper;
use HouseResidential as HouseResidentialModel;
use Schoolbox\Campus as CampusModel;
use Schoolbox\CampusMapper;
use Schoolbox\Role as RoleModel;
use Schoolbox\RoleMapper;
use Schoolbox\Sync\Provisioner\ValidUser;
use Schoolbox\User as UserModel;
use Schoolbox\User\Pronoun\Mapper as PronounMapper;
use Schoolbox\User\Pronoun\Model as PronounModel;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel;

/**
 * Importer for users into the Schoolbox connector.
 *
 * Saves into the following tables:
 * * user
 * * userCampus
 * * superusers
 *
 * @package Schoolbox\External\Importer
 */
class User extends AbstractImporter
{
    /**
     * @var Service
     */
    protected Service $passwordService;

    /**
     * @var UserMapper
     */
    protected UserMapper $userMapper;

    /**
     * @var array|RoleModel[]
     */
    protected $roleList = [];

    /**
     * @var array|YearLevel\Model[]
     */
    protected $yearLevelList = [];

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
     * @var array|PronounModel
     */
    protected array|PronounModel $pronounList = [];

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
     * Caches new user's usernames so we can detect multi new users
     * with the same username during dry-run.
     *
     * Maps 'username' => 'externalId'
     *
     * @var array<string, string>
     */
    protected array $newUsernameCache = [];

    /**
     * Documentation function for expected format
     *
     */
    public static function getFormat()
    {
        $format = [
            'Delete?'                      => 'Is this user to be deleted?',
            'Schoolbox User ID'                    => 'The user\'s internal Schoolbox ID number',
            'Username'                             => 'The unique name the user uses to log in',
            'External ID'                          => 'The user\'s ID number in your external system',
            'Title'                                => '',
            'First Name'                           => '',
            'Preferred Name'                       => '',
            'Surname'                              => '',
            'Pronoun'                              => '',
            'Position Title'                       => 'The user\'s position title (e.g. Admissions Manager)',
            'Role'                                 => 'The Schoolbox role for this user',
            'Campus'                               => 'A comma-separated list of campus names for this user.',
            'Password'                             => 'The user\'s password. If left blank, it will not be updated',
            'Alt Email'                            => '',
            'Year'                                 => '',
            'House'                                => '',
            'Residential House'                    => '',
            'E-Portfolio?'                         => '',
            'Hide Contact Details?'                => '',
            'Hide Timetable?'                      => '',
            'Email Address From Username?'         => '',
            'Use External Mail Client?'            => '',
            'Display Graph on Student Grade Card?' => '',
            'Superuser?'                           => '',
            'Account Enabled?'                     => '',
        ];

        return $format;
    }

    /**
     * Custom setup function
     */
    protected function setup()
    {
        $this->passwordService    = new Service();
        $this->userMapper         = new UserMapper();

        // Role list
        foreach (RoleMapper::getInstance()->disableCache()->findAll() as $role) {
            /** @var RoleModel $role */
            $this->roleList[$role->getName()] = $role;
        }

        // Year Level list
        foreach (YearLevel\Mapper::getInstance()->disableCache()->findAll() as $yearLevel) {
            /** @var YearLevel\Model $yearLevel */
            $this->yearLevelList[$yearLevel->getName()] = $yearLevel;
        }

        // Campus list
        foreach (CampusMapper::getInstance()->disableCache()->findAll() as $campus) {
            /** @var CampusModel $campus */
            $this->campusList[$campus->getName()] = $campus;
        }

        // House list
        foreach (HouseMapper::getInstance()->disableCache()->findAll() as $house) {
            /** @var HouseModel $house */
            $this->houseList[$house->getHouse()] = $house;
        }

        // Residential House list
        foreach (\HouseResidentialMapper::getInstance()->disableCache()->findAll() as $boardingHouse) {
            /** @var HouseResidentialModel $boardingHouse */
            $this->residentialHouseList[$boardingHouse->getHouse()] = $boardingHouse;
        }

        // Pronouns
        foreach (PronounMapper::getInstance()->disableCache()->findAll() as $pronoun) {
            /** @var PronounModel $pronoun */
            // Magic As that is how it is displayed in the system, and we should be matching on that
            $this->pronounList[$pronoun->__toString()] = $pronoun;
        }

        // Identifiers
        $this->userIdentifiers[] = new User\Identifier\UserId($this->userMapper);
        $this->userIdentifiers[] = new User\Identifier\Username($this->userMapper);
        $this->userIdentifiers[] = new User\Identifier\ExternalId($this->userMapper, $this->roleList);

        // Hydrators
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Username', 'setUsername', true);
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('External ID', 'setExternalId', false);
        $this->userHydrators[] = new User\Hydrator\Lookup('Role', 'setRole', $this->roleList);
        $this->userHydrators[] = new User\Hydrator\Lookup('Year', 'setYear', $this->yearLevelList);
        $this->userHydrators[] = new User\Hydrator\Password('Password', 'setPassword', $this->passwordService);
        $this->userHydrators[] = new User\Hydrator\FieldIgnoreEmpty('Password', 'setRetrievablePassword', false);
        $this->userHydrators[] = new User\Hydrator\ToggleField('E-Portfolio?', 'setEportfolioEnable');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Hide Contact Details?', 'setHideContacts');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Hide Timetable?', 'setHideTimetable');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Email Address From Username?', 'setLocalEmail');
        // This one might have to be inverted
        $this->userHydrators[] = new User\Hydrator\ToggleField('Use External Mail Client?', 'setUseInternalMail');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Display Graph on Student Grade Card?', 'setDisplayGradeGraph');
        // Not sure if superuser should be settable
        $this->userHydrators[] = new User\Hydrator\ToggleField('Superuser?', 'setSuperuser');
        $this->userHydrators[] = new User\Hydrator\ToggleField('Account Enabled?', 'setEnabled');
        $this->userHydrators[] = new User\Hydrator\Campus('Campus', $this->campusList);
        $this->userHydrators[] = new User\Hydrator\Field('Title', 'setTitle', true);
        $this->userHydrators[] = new User\Hydrator\Field('First Name', 'setFirstname', true);
        $this->userHydrators[] = new User\Hydrator\Field('Preferred Name', 'setPreferredName', true);
        $this->userHydrators[] = new User\Hydrator\Field('Surname', 'setLastname', true);
        $this->userHydrators[] = new User\Hydrator\Field('Position Title', 'setPositionTitle', true);
        $this->userHydrators[] = new User\Hydrator\Field('Alt Email', 'setAltEmail', true);
        $this->userHydrators[] = new User\Hydrator\Lookup('House', 'setSchoolHouse', $this->houseList);
        $this->userHydrators[] = new User\Hydrator\Lookup('Residential House', 'setResidentialHouse', $this->residentialHouseList);
        $this->userHydrators[] = new User\Hydrator\Lookup('Pronoun', 'setPronoun', $this->pronounList);
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
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        foreach ($this->reader as $i => $row) {
            // Step 1: identify user
            $user = $this->retrieveUser($row);

            /* retrieveUser performs a set of checks for a way to identify someone -
             * someone can be identified by any of the following fields which are
             * optional in the csv import:
             *
             * external id, username, user id
             *
             * Users have to be identified by one of those fields. When they can't,
             * null is returned.
             */
            if (!$user instanceof UserModel) {
                $this->log->warning('User could not be identified by any of the following fields: external id, user id or username - skipping');
                continue;
            }

            $originalUser = clone $user;

            // Step 2: update user based on fields
            $this->hydrateUser($row, $user);

            // Step 3: save user
            if ($this->parseStringBoolean($row['Delete?'] ?? 'false')) {
                $this->deleteUser($user);
            } else {
                if (empty($user->getId())) {
                    $this->createUser($user);
                } else {
                    $this->updateUser($user, $originalUser);
                }
            }

            unset($originalUser);
            unset($user);
        }
    }

    /**
     * Creates a user record.
     *
     */
    protected function createUser(UserModel $user)
    {
        if ($this->performUpdate) {
            try {
                if (!$this->userMapper->save($user)) {
                    $this->log->error('Could not create user!');
                } else {
                    $this->log->info(sprintf('Created user "%s" (%d)', $user->formatName(), $user->getId()));
                }
            } catch (\Exception $e) {
                $this->log->error('User not created: ' . $e->getMessage());
            }
        } else {
            $this->log->info(sprintf('Pretending to create user "%s"', $user->formatName()));

            /* Also figure out if you can actually change the user's details
             * based on the current hydration outcome
             */
            try {
                (new ValidUser($this->userMapper, $this->newUsernameCache))->sanityCheck($user);
            } catch (\UnexpectedValueException $e) {
                $this->log->error('User not updated: ' . $e->getMessage());
            }
        }
    }

    /**
     * Updates a user record.
     *
     */
    protected function updateUser(UserModel $user, UserModel $originalUser)
    {
        $diff = $this->userMapper->diff($originalUser, $user);
        if ($this->performUpdate) {
            try {
                if (!$this->userMapper->save($user)) {
                    $this->log->error('Could not update user!');
                } else {
                    $this->log->info(sprintf('Updated user "%s" (%d)', $user->formatName(), $user->getId()), ['diff' => $diff]);
                }
            } catch (\Exception $e) {
                $this->log->error('User not updated: ' . $e->getMessage());
            }
        } else {
            $this->log->info(sprintf('Pretending to update user "%s" (%d)', $user->formatName(), $user->getId()), ['diff' => $diff]);

            /* Also figure out if you can actually change the user's details
             * based on the current hydration outcome
             */
            try {
                (new ValidUser($this->userMapper, $this->newUsernameCache))->sanityCheck($user);
            } catch (\UnexpectedValueException $e) {
                $this->log->error('User not updated: ' . $e->getMessage());
            }
        }
    }

    /**
     * Deletes this user record.
     *
     */
    protected function deleteUser(UserModel $user)
    {
        if (!$user->getId()) {
            $this->log->warning(
                sprintf(
                    'Cannot delete user "%s" - they don\'t exist yet',
                    $user->formatName()
                ),
                ['user' => $user]
            );
            return;
        }
        if ($this->performUpdate) {
            try {
                if (!$this->userMapper->delete($user)) {
                    $this->log->error('Could not delete user!');
                } else {
                    $this->log->info(sprintf('Deleted user "%s" (%d)', $user->formatName(), $user->getId()));
                }
            } catch (\Exception $e) {
                $this->log->error('Could not delete user: ' . $e->getMessage());
            }
        } else {
            $this->log->info(sprintf('Pretending to delete user "%s" (%d)', $user->formatName(), $user->getId()));
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
