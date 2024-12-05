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
    public const LOCK_NAME = 'profileEditor_customwork_dsfg';


    /**
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'dsfg';


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

Profile Editor: DSFG Custom Work migration.


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
        $this->helper = new Helper($this->getDatabaseInternal(), true, $this->getLogger());

        $this->updateTerminology();
        $this->createButtons();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Renames Studies Code to NSN
     *
     * @return void
     */
    private function updateTerminology(): void
    {
        $this->getLogger()->debug('Renaming Studies Code to NSN');

        $this->getDatabaseInternal()->executeQuery("
            UPDATE localisation
            SET title = 'Studies Code',
                singular_form = 'NSN',
                plural_form = 'NSNs'
            WHERE term = 'studies_code'
            LIMIT 1
        ");
    }


    /**
     * They also had a custom button in the Student roletype which would have been
     * visible to Parent or Staff
     *
     * @return void
     */
    private function createButtons(): void
    {
        $profileButtonIds = [];

        /* Achievement Report
         */
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
                'Achievement',
                'Achievement',
                'progress',
                '/modules/custom/diocesan/achievementreport/[profileExternalId]'
            )";

        $this->getDatabaseInternal()->executeQuery($query);

        $profileButtonIds[] = (int)$this->getDatabaseInternal()->lastInsertId();

        /* Contacts
         */
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
                'Contacts',
                'Contacts',
                'progress',
                '/synergetic/contacts'
            )";

        $this->getDatabaseInternal()->executeQuery($query);

        $profileButtonIds[] = (int)$this->getDatabaseInternal()->lastInsertId();

        /* Attendances
         */
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
                'Attendance',
                'Attendance',
                'progress',
                '/synergetic/attendances2'
            )";

        $this->getDatabaseInternal()->executeQuery($query);

        $profileButtonIds[] = (int)$this->getDatabaseInternal()->lastInsertId();

        /* Assign relationships
         */
        $campusIds = $this->helper->fetchCampusIds();
        $roleIds = $this->helper->fetchRoleIds([Role::PARENT, Role::STAFF, Role::STUDENT]);

        foreach ($profileButtonIds as $profileButtonId) {
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
                implode(',', $roleIds),
                $profileButtonId
            ));

            $this->helper->createButtonRoleAvailability($profileButtonId, $roleIds);

            $this->getLogger()->info(sprintf(
                'Role visibility: assigning role ids %s to profile button %s',
                implode(',', $roleIds),
                $profileButtonId
            ));

            $this->helper->createButtonRoleVisibility($profileButtonId, $roleIds);
        }

        /* There's no real equivalent to this, but Documents (Academic Reports) was only
         * meant to be visible to SELF, but we don't have that option in Profile Buttons.
         * It's based on Roles.
         */
        $result = $this->getDatabaseInternal()->executeQuery("
            SELECT id
            FROM profile_button
            WHERE type = 'documentmanagement'
            AND roletype = 'student'
            LIMIT 1
        ");

        $row = $result->fetch(PDO::FETCH_ASSOC);

        $this->getLogger()->info(sprintf(
            'Role visibility: assigning role ids %s to profile button %s',
            implode(',', $roleIds),
            $row['id']
        ));

        $this->helper->createButtonRoleVisibility($row['id'], $roleIds);

        /* Rename Documents button
         */
        $this->getDatabaseInternal()->executeQuery("
            UPDATE profile_button
            SET text = 'Documents',
                title =  'Documents'
            WHERE type = 'documentmanagement'
            AND roletype = 'student'
        ");
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
