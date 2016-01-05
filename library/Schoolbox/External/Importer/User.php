<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Schoolbox\Mapper\Exception as MapperException;
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
     * A list of user identifier classes - each one will in turn attempt to
     * retrieve a UserModel from the row.
     *
     * @var array|User\Identifier\IdentifierInterface[]
     */
    protected $userIdentifiers = [];

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
        $this->userIdentifiers[] = new User\Identifier\UserId($this->userMapper);
        $this->userIdentifiers[] = new User\Identifier\Username($this->userMapper);
        $this->userIdentifiers[] = new User\Identifier\ExternalId($this->userMapper, $this->roleMapper);
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
                continue;
            }
            if ($user->getId()) {
                $this->log->info(sprintf('User found (ID: #%d)', $user->getId()));
            } else {
                $this->log->info('User not found - creating new user');
            }

            // Step 2: are they deleted?
            // Users aren't currently soft-deletable, so they stay deleted.
            if ($this->parseStringBoolean($row['Delete?'])) {
                $result[] = [
                    'user'   => $user,
                    'delete' => true,
                ];
                continue;
            }

            // Step 3: update user based on fields

            // Identifier fields and password:
            // set only if they are not blank
            if (strlen($row['Username'])) {
                $user->setUsername($row['Username']);
            }
            if (strlen($row['External ID'])) {
                $user->setExternalId($row['External ID']);
            }
            if (strlen($row['Role'])) {
                $user->setRole($row['Role']);
            }
            if (strlen($row['Password'])) {
                // Does this need to be encoded?
                $user->setPassword($row['Password']);
            }

            // Toggle fields: set if not blank
            $eportfolio = $this->parseStringBoolean($row['E-Portfolio?']);
            if (isset($eportfolio)) {
                $user->setEportfolioEnable($eportfolio);
            }
            $emailFromUsername = $this->parseStringBoolean($row['Email Address From Username?']);
            if (isset($emailFromUsername)) {
                $user->setLocalEmail($emailFromUsername);
            }
            $externalEmailClient = $this->parseStringBoolean($row['Use External Mail Client?']);
            if (isset($externalEmailClient)) {
                $user->setUseInternalMail(!$externalEmailClient);
            }
            $webmail = $this->parseStringBoolean($row['Enable Webmail Tab?']);
            if (isset($webmail)) {
                $user->setWebmail($webmail);
            }
            // Not sure if superuser should be settable
            $superuser = $this->parseStringBoolean($row['Superuser?']);
            if (isset($superuser)) {
                $user->setSuperuser($superuser);
            }
            $enabled = $this->parseStringBoolean($row['Account Enabled?']);
            if (isset($enabled)) {
                $user->setEnabled($enabled);
            }

            // Campus: split and set
            // Default campus: first in list
            if (isset($row['Campus'])) {
                $campusList = array_map('trim', explode(',', $row['Campus']));
                // Set based on code or name? Maybe name.
            }

            // Other fields: set them if they exist at all
            if (isset($row['Title'])) {
                $user->setTitle($row['Title']);
            }
            if (isset($row['First Name'])) {
                $user->setFirstname($row['First Name']);
            }
            if (isset($row['Surname'])) {
                $user->setLastname($row['Surname']);
            }
            if (isset($row['Alt Email'])) {
                $user->setAltEmail($row['Alt Email']);
            }
            if (isset($row['Year'])) {
                $user->setYear($row['Year']);
            }
            if (isset($row['House'])) {
                // get and set model
                $user->setSchoolHouse($row['House']);
            }
            if (isset($row['Residential House'])) {
                // get and set model
                $user->setResidentialHouse($row['Residential House']);
            }

            $result[] = [
                'user'   => $user,
                'delete' => false,
            ];

            // Step 4: update extended fields if using SB connector
            if ($this->isExtendedFormatUsed()) {
                // update
            }
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
