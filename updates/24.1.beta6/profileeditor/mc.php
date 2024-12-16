<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Admin\Profile\Editor\Cli\Helper;
use Schoolbox\Admin\Profile\Editor\Payload;
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
    public const LOCK_NAME = 'profileEditor_customwork_mc';


    /**
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'mc';


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

Profile Editor: MC Custom Work migration.


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

        /* Renames Form Teacher ("Class Teacher") to "Head of Year"
         * They're reusing StudentTutorDescription to "Class Teacher" and alternatively, "Mentor"
         * which is a little confusing...
         */
        $this->helper->renameField('formTeacher', 'Head of Year', 'Heads of Year');

        /* Update field configuration
         *
         * Hide StudentBoarder and StudentForm from everyone
         */
        $campusIds = [];
        $roletypes = [];

        $this->helper->updateField(Role::STUDENT, 'StudentBoarder', $campusIds, $roletypes);
        $this->helper->updateField(Role::STUDENT, 'StudentForm', $campusIds, $roletypes);

        /* Chesed Hours custom profile field
         */
        $this->createFields();

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Their connector surfaces ChesedHours
     * optionally displaying it in the profile screens if they're meaningful
     *
     * @return void
     */
    private function createFields(): void
    {
        $profileFieldIds = [];

        $query = "
            INSERT IGNORE INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student','ChesedHours','Chesed Hours',(
            SELECT MAX(sequence)+1
                FROM profile_field pf
                WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1)
        ";

        $this->getDatabaseInternal()->executeQuery($query);

        $profileFieldIds[] = (int)$this->getDatabaseInternal()->lastInsertId();

        /* Preset availability and visibility requirements
         * By default it was visible to Parents of the student, and staff members
         *
         * Find the field we just made in the surest way we know
         */
        $roletypes = [Role::STAFF, Role::PARENT, Payload::ROLETYPE_SELF];
        $campusIds = $this->helper->fetchCampusIds();

        /* Assign relationships
         */
        foreach ($profileFieldIds as $profileFieldId) {
            $this->getLogger()->info(sprintf(
                'Campus availability: assigning campus ids %s to profile field %s',
                implode(',', $campusIds),
                $profileFieldId
            ));

            $this->helper->createFieldCampusAvailability($profileFieldId, $campusIds);

            $this->getLogger()->info(sprintf(
                'Roletype visibility: assigning roletypes %s to profile field %s',
                implode(',', $roletypes),
                $profileFieldId
            ));

            $this->helper->createFieldRoletypeVisibility($profileFieldId, $roletypes);
        }
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
