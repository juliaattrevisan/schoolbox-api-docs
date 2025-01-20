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
    public const LOCK_NAME = 'profileEditor_repair_custom_buttons_self';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * The school codes that this script is applicable to.
     * This is ALL instances that were updated to 24.1.x before SA-19205 (24.1.3)
     */
    private const SCHOOL_CODES = [
        "PS",
        "SCC",
        "SCC",
        "DSFG",
        "SLC",
        "VLC",
        "BRGRS",
        "SSCL",
        "INVEST",
        "LCGS",
        "STCOL",
        "KCC",
        "ECC",
        "NGSL",
        "SCGS",
        "ACGS",
        "AAS",
        "DCL",
        "WILDER",
        "CCGS",
        "NEWIC",
        "HTLC",
        "CC",
        "SK",
        "WC",
        "PCI",
        "SPCEDY",
        "EMC",
        "TCC",
        "SPCENC",
        "THS",
        "BGDCI",
        "GSLC",
        "TOWER",
        "SOC",
        "SJAC",
        "PLCV",
        "OSS",
        "SCG",
        "SPCNT",
        "KC",
        "IMM",
        "SPCEDL",
        "SPCEGF",
        "SPCECN",
        "SPCEPS",
        "SPCT",
        "TSC",
        "KARDIC",
        "SPC",
        "TLCMILD",
        "AGSBOT",
        "TSSA",
        "MS",
        "TBAS",
        "ASAS",
        "DHSZ",
        "SPCEFL",
        "TESIS",
        "WCC",
        "BHCS",
        "SC",
        "OFGSL",
        "MCSL",
        "DECS",
        "MALVC",
        "WMAC",
        "SOMVIL",
        "GEEGRS",
        "TRIGS",
        "KRBS",
        "MBSJC",
        "SGGS",
        "SHC",
        "COOLBCC",
        "PCL",
        "PACL",
        "MEGGS",
        "DCSPX",
        "TSAC",
        "MC",
    ];


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


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

Profile Editor: Repairs custom buttons inadvertently being visible to all student roles.

For instances that were updated before SA-19205 (24.1.3), this script will remove all SELF
settings from custom student buttons since we don't have enough data to determine the correct
configuration anymore for these instances.

HELP;
            return;
        }


        /* Exit early if you're doing this on a school
         */
        if (!in_array(strtoupper($this->getConfig()->get('site_school_code')), self::SCHOOL_CODES, true)) {
            $this->getLogger()->debug('This customisation does not apply to this school code; exiting.');

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

        /* For all custom student buttons, we need to disable SELF
         */
        $profileButtonMapper = \Schoolbox\Profile\Button\Mapper::getInstance();

        $customButtons = $profileButtonMapper->findByColumnList([
            'roletype' => Role::STUDENT,
            'type' => 'custom'
        ]);

        foreach ($customButtons as $customButton) {
            if ($customButton->getSelfEnabled() === 0) {
                continue; // Nothing to do
            }

            $this->getLogger()->info(sprintf(
                'Custom button %s has SELF enabled; disabling it.',
                $customButton->getText()
            ));

            $customButton->setSelfEnabled(0);
            $profileButtonMapper->save($customButton);
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
