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
    public const LOCK_NAME = 'profileEditor_customwork_ccca';


    /**
     * This script requires a particular school code.
     */
    private const SCHOOL_CODE = 'ccca';


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

Profile Editor: CCCA Custom Work migration.


HELP;
            return;
        }

        /* Exit early if you're doing this on the wrong school
         */
        if (self::SCHOOL_CODE !== $this->getConfig()->get('site_school_code')) {
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

        $this->createFields();

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Their connector surfaces a barcode (external id) and a student mobile phone
     * optionally displaying it in the profile screens if they're meaningful
     *
     * @return void
     */
    private function createFields(): void
    {
        /* Prepare this statement for mass production
         */
        $statement = $this->getDatabaseInternal()->prepare("
            INSERT IGNORE INTO `profile_field`
            (roletype, field, display_as, sequence, enabled, term, icon, css, click_to_reveal, is_external)
            VALUES
            ('student',:field, :displayAs, (
            SELECT MAX(sequence)+1
                FROM profile_field pf
                WHERE roletype = 'student'
            ),1,NULL,NULL,NULL,0,1)
        ");

        /* We're going to make these two new fields
         */
        $this->createStudentMobilePhone($statement);

        /* This is combined with the default rendering of externalId instead.
         * I tried to render them separately but the whole frontend seems to be geared towards
         * (roletype, field) being a unique tuple.
         */
//        $this->createBarcode($statement);
    }


    /**
     * Creates a Student Mobile Phone field
     *
     * @param PDOStatement $statement
     * @return void
     */
    private function createStudentMobilePhone(\PDOStatement $statement): void
    {
        /* Mobile phone was only available to staff
         */
        $statement->execute([
            'field' => 'StudentMobilePhone',
            'displayAs' => 'StudentMobilePhone',
        ]);

        /* Preset availability and visibility requirements
         */
        $profileFieldId = (int)$this->getDatabaseInternal()->lastInsertId();

        /* I suppose this was available to everyone initially but I don't really know for sure:
         * imho it seems like it would have been used by the student themselves (not other students)
         * and staff members, making roletypes STAFF and SELF.
         */
        $roletypes = [Role::STAFF, \Schoolbox\Admin\Profile\Editor\Payload::ROLETYPE_SELF];
        $campusIds = $this->helper->fetchCampusIds();

        /* Assign relationships
         */
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


//    /**
//     * Creates a Barcode field that renders the external id as a code39 barcode
//     *
//     * @param PDOStatement $statement
//     * @return void
//     */
//    private function createBarcode(\PDOStatement $statement): void
//    {
//        /* Barcode was only available to staff
//         */
//        $statement->execute([
//            'field' => 'externalId',
//            'displayAs' => 'Barcode',
//        ]);
//
//        /* Preset availability and visibility requirements
//         */
//        $profileFieldId = (int)$this->getDatabaseInternal()->lastInsertId();
//
//        $roletypes = [Role::STAFF];
//        $campusIds = $this->helper->fetchCampusIds();
//
//        /* Assign relationships
//         */
//        $this->getLogger()->info(sprintf(
//            'Campus availability: assigning campus ids %s to profile field %s',
//            implode(',', $campusIds),
//            $profileFieldId
//        ));
//
//        $this->helper->createFieldCampusAvailability($profileFieldId, $campusIds);
//
//        $this->getLogger()->info(sprintf(
//            'Roletype visibility: assigning roletypes %s to profile field %s',
//            implode(',', $roletypes),
//            $profileFieldId
//        ));
//
//        $this->helper->createFieldRoletypeVisibility($profileFieldId, $roletypes);
//    }

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
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
