<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
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
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * Is the extended format used?
     *
     * (It is used when using the Schoolbox "external" connector).
     *
     * @return bool
     */
    public static function isExtendedFormatUsed()
    {
        // Sigh
        return \Config::get('external_db') === 'schoolbox';
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
        if (self::isExtendedFormatUsed()) {
            $format += [
                // user_relation
                "Child External IDs"           => '',
                // user_profile
                "Date of Birth"                => '',
                "Year Level"                   => '',
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
        $this->roleMapper = new RoleMapper();
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @return array
     */
    protected function parse()
    {
        $result = [];
        foreach ($this->reader as $i => $row) {
            $this->log->info("Parsing row $i...");

            // Step 1: identify user
            $user = $this->retrieveUser($row);
            if (!$user instanceof UserModel) {
                $this->log->warning('User could not be identified - skipping');
            }

            // Step 2: are they deleted?
            // Users aren't currently soft-deletable, so they stay deleted.

            // Step 3: update user

            // Step 4: update extended fields
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
        if (strlen($userId = $row['Schoolbox User ID'])) {
            // Update user with this ID.
            // If they don't exist - skip
            try {
                return $this->userMapper->findById($userId);
            } catch (MapperException $e) {
                $this->log->warning(sprintf('User with ID "%s" not found', $userId));
                return null;
            }
        } elseif (strlen($username = $row['Username'])) {
            // Update user with a this username.
            // If they don't exist - create
            try {
                return $this->userMapper->findOneByColumn('username', $username);
            } catch (MapperException $e) {
                $this->log->info(sprintf('User with username "%s" not found - will create new', $username));
                return $this->userMapper->createModel();
            }
        } elseif (strlen($externalId = $row['External ID']) && strlen($row['Role'])) {
            // Update user who has this external ID, and this role type.
            // If they don't exist - create
            try {
                /** @var RoleModel $role */
                $role = $this->roleMapper->findOneByColumn('name', $row['Role']);
            } catch (MapperException $e) {
                $this->log->warning(sprintf('Role "%s" not found', $row['Role']));
                return null;
            }
            try {
                return $this->userMapper->findByExternalId($externalId, $role->getType());
            } catch (MapperException $e) {
                $this->log->info(
                    sprintf(
                        'User with external ID "%s" not found for role type "%s" - will create new',
                        $externalId,
                        $role->getType()
                    )
                );
                return $this->userMapper->createModel();
            }
        } else {
            // User could not be identified - skip
            $this->log->warning('User has no identifying fields');
            return null;
        }
    }

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     */
    public function save()
    {
        $result = $this->parse();
        foreach ($result as $user) {
            if ($this->performUpdate) {
                $this->log->info('Saving user ');
                if ($this->userMapper->save($user)) {
                    $this->log->info(sprintf('User saved (ID: #%d)', $user->getId()));
                } else {
                    $this->log->error('Could not save user!');
                }
            } else {
                $this->log->info('Pretending to save user');
            }
        }
    }
}
