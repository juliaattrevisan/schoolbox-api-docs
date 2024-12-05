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
    public const LOCK_NAME = 'profileEditor_customwork_kcc';


    /**
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'kcc';


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

Profile Editor: KCC Custom Work migration.


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

        $this->createButtons();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * They also had a custom button in the Student roletype
     *
     * @return void
     */
    private function createButtons(): void
    {
        /* We're gonna make some buttons with this prepared statement
         */
        $createButtonStatement = $this->getDatabaseInternal()->prepare("
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
                :title,
                :text,
                :icon,
                :url
            )
        ");

        $customButtons = [
            [
                'title' => 'TASS Profile',
                'text' => 'TASS Profile',
                'icon' => 'progress',
                /* A deeplink to Student Profile in TASS
                 * https://github.com/TheAlphaSchoolSystemPTYLTD/deep-linking/blob/master/kiosk/student.profile.md
                 */
                'url' => sprintf(
                    '%s/%s',
                    rtrim($this->getConfig()->get('external_staff_portal'), '/'),
                    'kiosk/login.cfm?go={"target":"student.profile","studcode":"[profileExternalId]","prod_menu":"Y"}'
                )
            ],
        ];

        foreach ($customButtons as $customButton) {
            $createButtonStatement->execute([
                'title' => $customButton['title'],
                'text' => $customButton['text'],
                'icon' => $customButton['icon'],
                'url' => $customButton['url'],
            ]);

            /* Preset availability and visibility requirements
             */
            $profileButtonId = (int)$this->getDatabaseInternal()->lastInsertId();

            /* The button exists on student profiles for staff members to access
             */
            $availabilityRoleIds = $this->helper->fetchRoleIds([Role::STUDENT]);
            $visibilityRoleIds = $this->helper->fetchRoleIds([Role::STAFF]);
            $campusIds = $this->helper->fetchCampusIds();

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
