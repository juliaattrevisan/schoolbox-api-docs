<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Profile\Editor\Cli\Helper;
use Schoolbox\Application\CliTool;
use Schoolbox\Role;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'profileEditor_customwork_ms';


    /**
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'ms';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Somewhat convenient access to inconvenient queries
     *
     * @var Helper
     */
    private Helper $helper;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Profile Editor: MS Custom Work migration.


HELP;
            return;
        }

        /* Exit early if you're doing this on the wrong school
         */
        if (self::SCHOOL_CODE !== strtolower($this->getConfig()->get('site_school_code'))) {
            $this->getLogger()->debug(sprintf(
                'This customisation requires the %s school code; exiting.',
                self::SCHOOL_CODE
            ));

            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        /* Always runs in Perform mode
         */
        $this->helper = new Helper($this->getDatabaseInternal(), true);

        $this->removeField();
        $this->createButtons();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * This removes a Field by destroying all its availability/visibility relationships
     *
     * @return void
     */
    private function removeField(): void
    {
        /* Remove Grades button
         */
        $tables = [
            'profile_field_campus_availability',
            'profile_field_roletype_visibility',
        ];

        /* Un-assign relationships
         */
        foreach ($tables as $table) {
            $this->getLogger()->info(sprintf(
                'Removing relationships with tutorTeacher field in %s',
                $table
            ));

            $this->getDatabaseInternal()->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = 'tutorTeacher' AND roletype = 'student'
                )
            ", $table));

            $this->getLogger()->info(sprintf(
                'Removing relationships with StudentTutorDescription field in %s',
                $table
            ));

            $this->getDatabaseInternal()->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = 'StudentTutorDescription' AND roletype = 'student'
                )
            ", $table));

            $this->getLogger()->info(sprintf(
                'Removing relationships with hohTeacher field in %s',
                $table
            ));

            $this->getDatabaseInternal()->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = 'hohTeacher' AND roletype = 'student'
                )
            ", $table));

            $this->getLogger()->info(sprintf(
                'Removing relationships with StudentBoarder field in %s',
                $table
            ));

            $this->getDatabaseInternal()->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = 'StudentBoarder' AND roletype = 'student'
                )
            ", $table));

            $this->getLogger()->info(sprintf(
                'Removing relationships with StudentForm field in %s',
                $table
            ));

            $this->getDatabaseInternal()->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = 'StudentForm' AND roletype = 'student'
                )
            ", $table));
        }
    }


    /**
     * They also had a custom button in the Student roletype which would have been
     * visible to anyone except parents of the student who were restricted by the
     * restrict_parent_grades config.
     *
     * But I think it should be staff and parents of the student, ultimately
     *
     * @return void
     */
    private function createButtons(): void
    {
        $query = "
            INSERT IGNORE INTO profile_button
            (roletype, type, sort_order, title, text, icon, url)
            VALUES (
                'student',
                'custom',
                (
                    SELECT MAX(sort_order)+1
                    FROM profile_button pb
                    WHERE roletype = 'student'
                ),
                'eReport',
                'eReport',
                'tick',
                '/modules/custom/meriden/pastoral/[profileId]'
            )";

        $this->getDatabaseInternal()->executeQuery($query);

        /* Preset availability and visibility requirements
         * By default it was visible to Parents of the student, and staff members
         *
         * Find the button we just made in the surest way we know
         */
        $profileButtonId = (int)$this->getDatabaseInternal()->lastInsertId();

        $campusIds = $this->helper->fetchCampusIds();
        $availabilityRoleIds = $this->helper->fetchRoleIds([Role::STUDENT]);
        $visibilityRoleIds = $this->helper->fetchRoleIds([Role::PARENT, Role::STAFF]);

        /* Assign relationships
         */
        $this->getLogger()->info(sprintf(
            'Campus availability: assigning campus ids %s to profile button %s',
            implode(',', $campusIds),
            $profileButtonId
        ));

        $this->helper->createButtonCampusAvailability($profileButtonId, $campusIds);

        $this->getLogger()->info(sprintf(
            'Campus visibility: assigning campus ids %s to profile button %s',
            implode(',', $campusIds),
            $profileButtonId
        ));

        $this->helper->createButtonCampusVisibility($profileButtonId, $campusIds);

        $this->getLogger()->info(sprintf(
            'Role availability: assigning role ids %s to profile button %s',
            implode(',', $availabilityRoleIds),
            $profileButtonId
        ));

        $this->helper->createButtonRoleAvailability($profileButtonId, $availabilityRoleIds);

        $this->getLogger()->info(sprintf(
            'Role visibility: assigning role ids %s to profile button %s',
            implode(',', $visibilityRoleIds),
            $profileButtonId
        ));

        $this->helper->createButtonRoleVisibility($profileButtonId, $visibilityRoleIds);
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
