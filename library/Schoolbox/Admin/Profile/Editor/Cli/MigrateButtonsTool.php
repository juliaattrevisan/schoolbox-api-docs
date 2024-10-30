<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Schoolbox\Application\CliTool;

class MigrateButtonsTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const MY_TOOL_LOCK_NAME = 'profileEditorMigrateButtons';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 10;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --system
     *
     * Migrate our previously hardcoded system buttons into their corresponding roletype(s)
     */
    private const OPT_LONG_TYPE_SYSTEM = 'system';


    /**
     * --custom
     *
     * Migrate custom buttons into their corresponding roletype(s)
     */
    private const OPT_LONG_TYPE_CUSTOM = 'custom';


    /**
     * Consts used - including the "self" pseudo-roletype
     */
    private const ROLETYPE_SELF = \Schoolbox\Admin\Profile\Editor\Payload::ROLETYPE_SELF;
    private const ROLETYPE_STUDENT = \Schoolbox\Role::STUDENT;
    private const ROLETYPE_PARENT = \Schoolbox\Role::PARENT;
    private const ROLETYPE_STAFF = \Schoolbox\Role::STAFF;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_TYPE_SYSTEM,
            self::OPT_LONG_TYPE_CUSTOM,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Profile Editor System/Custom Button Migration

This creates Profile Button equivalents for our previously-hardcoded system buttons.
It also migrates System and Custom buttons into their corresponding roletypes based
on whatever role(s) were enabled for them.

Usage: php migrate-buttons.php -vvv --perform


HELP;
            return;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::MY_TOOL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            return;
        }

        try {
            $this->getLogger()->info('Profile Editor Button Migration.');
            $this->getLogger()->info('');
        } catch (\Alaress\Lock\LockRefreshException $e) {
            /* Lock TTL expired; just leave politely
             */
            return;
        }

        /* Announce conclusion
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }

        /* Announce lock release
          */
        $this->getLogger()->debug('Releasing lock');

        $lock->release();
    }


    /**
     * Does not require special file permissions
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
