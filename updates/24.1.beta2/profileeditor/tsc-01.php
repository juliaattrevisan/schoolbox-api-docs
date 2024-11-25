<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Profile\Editor\Cli\Helper;
use Schoolbox\Application\CliTool;

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
$app = new class extends CliTool\App {
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'profileEditor_customwork_tsc';


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
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'tsc';


    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $campusAvailabilityInsertStatement;
    private \PDOStatement $campusVisibilityInsertStatement;
    private \PDOStatement $roleAvailabilityInsertStatement;
    private \PDOStatement $roleVisibilityInsertStatement;


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

Profile Editor: TSC Custom Work migration.


HELP;
            return;
        }

        /* Exit early if you're doing this on the wrong school
         */
        if (self::SCHOOL_CODE !== $this->getConfig()->get('site_school_code')) {
            $this->getLogger()->warning(sprintf(
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

        $this->helper = new Helper($this->getDatabaseInternal());

        $this->prepare();
        $this->insertCustomFields();
        $this->insertCustomButtons();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Set up some prepared statements
     *
     * @return void
     */
    private function prepare(): void
    {
        $this->campusAvailabilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->campusVisibilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->roleAvailabilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");

        $this->roleVisibilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");
    }


    /**
     * Their connector surfaces LECaseManagerName, LECaseManagerUserId
     * optionally displaying it in the profile screens if they're meaningful
     *
     * @return void
     */
    private function insertCustomFields(): void
    {
        $query = "
            INSERT IGNORE INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student','LECaseManagerName','Learning Enrichment Case Manager',(
            SELECT MAX(sequence)+1
            FROM profile_field pf
            WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1);
        ";

        $this->getDatabaseInternal()->executeQuery($query);

        /* Despite this field existing, it'll never render to users: a customised student.twig
         * intercepts the default rendering
         */
        $query = "
            INSERT IGNORE INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student','LECaseManagerID','Learning Enrichment Case Manager ID',(
            SELECT MAX(sequence)+1
                FROM profile_field pf
                WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1)
        ";

        $this->getDatabaseInternal()->executeQuery($query);
    }


    /**
     * They also had a custom button in the Student roletype which would have been
     * visible to Parent or Staff
     *
     * @return void
     */
    private function insertCustomButtons(): void
    {
        $query = "
            INSERT INTO profile_button
            (roletype, type, sort_order, title, text, icon, url)
            VALUES (
                'student',
                'custom',
                (
                    SELECT MAX(sort_order)+1
                    FROM profile_button pb
                    WHERE roletype = 'student'
                ),
                'Results',
                'Results',
                'result',
                'https://dashboard.tsc.nsw.edu.au/login/index/[profileId]'
            )";

        $this->getDatabaseInternal()->executeQuery($query);

        /* Preset availability and visibility requirements
         * By default it was visible to Parents of the student, and staff members
         *
         * Find the button we just made in the surest way we know
         */
        $profileButtonId = $this->getDatabaseInternal()->lastInsertId();

        $roleIds = $this->helper->fetchRoleIds([\Schoolbox\Role::PARENT, \Schoolbox\Role::STAFF]);
        $campuses = $this->helper->fetchCampusIds();

        /* Assign relationships
         *
         * Technically speaking, all these hardcoded system buttons would have
         * been available in every campus for every role, subject to further
         * checks by routeLookupIfAuthorized calls. Schools have the option to
         * nip it in the bud if they want to prevent viewers from seeing a
         * button by unticking their role(s) as per the ui
         */
        $this->createCampusAvailability($profileButtonId, $candidate['availability']['campus']);
        $this->createCampusVisibility($profileButtonId, $candidate['visibility']['campus']);
        $this->createRoleAvailability($profileButtonId, $candidate['availability']['role']);
        $this->createRoleVisibility($profileButtonId, $candidate['visibility']['role']);

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
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
