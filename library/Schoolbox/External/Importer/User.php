<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Schoolbox\Mapper;
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
        }
        return $result;
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
