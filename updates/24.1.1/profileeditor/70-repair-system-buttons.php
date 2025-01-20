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
    public const LOCK_NAME = 'profileEditor_repair_system_buttons';


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

Profile Editor: Repairs system buttons inadvertently being visible to all student roles.
We apply the SELF pseudo-roletype for buttons instead.

Note that custom works will have turned off some system buttons entirely: we still try
to obey those changes here.


HELP;
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

        /* The current school
         */
        $schoolCode = strtolower($this->getConfig()->get('site_school_code'));

        /* Everyone
         */
        $allRoleIds = $this->helper->fetchRoleIds([
            Role::STAFF,
            Role::PARENT,
            Role::STUDENT
        ]);

        /* Staff users, and parents of the student
         */
        $staffParentRoleIds = $this->helper->fetchRoleIds([
            Role::STAFF,
            Role::PARENT
        ]);

        /* Parents of the student;
         */
        $parentRoleIds = $this->helper->fetchRoleIds([
            Role::PARENT,
        ]);

        /* Staff only
         */
        $staffRoleIds = $this->helper->fetchRoleIds([
            Role::STAFF
        ]);

        /* For every system button,
         * Reset student visibilities
         */
        $systemButtons = [
            /**
             * Student profile buttons
             */
            [
                'roletype' => Role::STUDENT,
                'type' => 'eportfolio',
                'selfEnabled' => 1,
                'visibilities' => $allRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'duework',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [
                    'geegrs', 'lgs', 'staloy'
                ],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'grades',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [
                    'wsl', 'geegrs', 'lgs'
                ],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'pastoral',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'calendar',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'timetable',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [
                    'scecgs'
                ],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'documentmanagement',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STUDENT,
                'type' => 'geosnapshot',
                'selfEnabled' => 1,
                'visibilities' => $staffParentRoleIds,
                'exclusions' => [],
            ],
            /* Should not have self,
             * Students can't email all staff,
             * and parents shouldn't email all staff.
             */
            [
                'roletype' => Role::STUDENT,
                'type' => 'emailstaff',
                'selfEnabled' => 0,
                'visibilities' => $staffRoleIds,
                'exclusions' => [],
            ],
            /* Should not have self,
             * Parents can't email all parents.
             */
            [
                'roletype' => Role::STUDENT,
                'type' => 'emailparents',
                'selfEnabled' => 0,
                'visibilities' => $staffRoleIds,
                'exclusions' => [],
            ],

            /** Staff profile buttons
             */
            [
                'roletype' => Role::STAFF,
                'type' => 'eportfolio',
                'selfEnabled' => 0,
                'visibilities' => $allRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STAFF,
                'type' => 'calendar',
                'selfEnabled' => 1,
                'visibilities' => $staffRoleIds,
                'exclusions' => [],
            ],
            [
                'roletype' => Role::STAFF,
                'type' => 'timetable',
                'selfEnabled' => 1,
                'visibilities' => $staffRoleIds,
                'exclusions' => [],
            ],

            /**
             * Parent profile buttons
             */
            [
                'roletype' => Role::PARENT,
                'type' => 'eportfolio',
                'selfEnabled' => 1,
                'visibilities' => $allRoleIds,
                'exclusions' => [],
            ],
            /* Parents see their own calendar, but nobody else's
             * I suppose.........
             */
            [
                'roletype' => Role::PARENT,
                'type' => 'calendar',
                'selfEnabled' => 1,
                'visibilities' => [],
                'exclusions' => [],
            ],
        ];

        /* Remove all student role visibilities
         * and enable SELF pseudo-roletype
         */
        $profileButtonMapper = \Schoolbox\Profile\Button\Mapper::getInstance();

        /* A purger
         */
        $deleteStatement = $this->getDatabaseInternal()->prepare("
            DELETE FROM profile_button_role_visibility
            WHERE profile_button_id = :profileButtonId
        ");

        foreach ($systemButtons as $systemButton) {
            /* EXCEPT WHEN you're a school with a custom work that removed this button entirely
             * then ensure that we maintain that change.
             */
            if (\in_array($schoolCode, $systemButton['exclusions'], true)) {
                $this->getLogger()->info(sprintf(
                    'System button %s had a custom work: skipping default reassignment',
                    $systemButton['type']
                ));

                continue;
            }

            $this->getLogger()->info(sprintf(
                'System button %s receiving SELF reassignment',
                $systemButton['type']
            ));

            /* Update visibility to include SELF etc.
             */
            $button = $profileButtonMapper->findOneByColumnList([
                'roletype' => $systemButton['roletype'],
                'type' => $systemButton['type']
            ]);

            $button->setSelfEnabled($systemButton['selfEnabled']);

            $profileButtonMapper->save($button);

            /* Remove student visibilities
             */
            $deleteStatement->execute([
                'profileButtonId' => $button->getId()
            ]);

            $profileButtonId = $button->getId();

            $this->helper->createButtonRoleVisibility($profileButtonId, $systemButton['visibilities']);
        }

        /* Process all the user-manufactured custom buttons, enabling SELF
         * instead of all the student roles which we did mistakenly
         */
        $customButtons = $profileButtonMapper->findByColumnList([
            'roletype' => Role::STUDENT,
            'type' => 'custom'
        ]);

        /* All student roles
         */
        $studentRoleIds = implode(',', $this->helper->fetchRoleIds([
            Role::STUDENT
        ], false));

        foreach ($customButtons as $customButton) {
            $profileButtonId = $customButton->getId();

            /**
             * If there is no visibility for any student role currently, skip this button
             * We don't want to make buttons visible to students that previously weren't
             */

            if ('' === $studentRoleIds) {
                break; // no student roles, nothing to do
            }

            $existingStudentVisibilities = $this->getDatabaseInternal()->executeQuery("
                SELECT role_id
                FROM profile_button_role_visibility
                WHERE profile_button_id = {$profileButtonId}
                AND role_id IN ({$studentRoleIds})
            ");

            if ($existingStudentVisibilities->rowCount() === 0) {
                $this->getLogger()->info(sprintf(
                    'Custom button %s NOT receiving SELF reassignment (no student visibilities)',
                    $customButton->getText()
                ));
                continue;
            }

            /**
             * Since we have student visibilities, we need to remove them and enable SELF instead
             */

            $this->getLogger()->info(sprintf(
                'Custom button %s receiving SELF reassignment (has student visibilities)',
                $customButton->getText()
            ));

            /* Update visibility to include SELF etc.
             */
            $customButton->setSelfEnabled(1);

            $profileButtonMapper->save($customButton);

            /* Remove all student roles
             */
            $this->getLogger()->info(sprintf(
                'Custom button %s removing role ids %s from visibilities',
                $customButton->getText(),
                $studentRoleIds
            ));

            $this->getDatabaseInternal()->executeQuery("
                DELETE FROM profile_button_role_visibility
                WHERE profile_button_id = {$profileButtonId}
                AND role_id IN ({$studentRoleIds})
            ");
        }

        /* Announce lock release
         */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
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
