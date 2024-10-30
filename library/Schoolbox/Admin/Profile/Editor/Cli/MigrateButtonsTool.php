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

            /* Figure out which roletype corresponds to each role
             */
            $roletypeLookup = $this->generateRoletypeLookup();

            /* Get a list of buttons to inspect - users can specify --system,
             * --custom, or both
             */
            $candidates = $this->generateCandidates();

            /* Each button can have zero or more roles associated with it
             * Inspect each button's relationships to find out which roles,
             * and thus which role types, they need. Find a unique list of
             * roletypes; we need to clone thus button out for each one.
             *
             * We'll use role visibility as the most definitive way to figure
             * out corresponding roletype(s) for a button. Campus is too broad
             * a stroke and roles have a direct relationship with a roletype.
             */
            print_r($roletypeLookup);
            print_r($candidates);
            foreach ($candidates as $candidate) {
                foreach ($candidate['_roles'] as $roleId) {
                    if (self::ROLETYPE_STUDENT === $roletypeLookup[$roleId]) {
                        /* Create a button in the student roletype
                         * TODO if there's only one $roleId you could just update
                         *  the existing row rather than arbitrarily spawn a new one
                         */
                        $this->getLogger()->info(sprintf(
                            'Migrating "%s" to %s roletype',
                            $candidate['title'],
                            self::ROLETYPE_STUDENT
                        ));

                        $this->getLogger()->debug(sprintf(
                            'Assigning roletype %s via lookup, using role id %s',
                            self::ROLETYPE_STUDENT,
                            $roleId
                        ));
                    } elseif (self::ROLETYPE_STAFF === $roletypeLookup[$roleId]) {
                        /* Create a button in the staff roletype
                         * TODO if there's only one $roleId you could just update
                         *  the existing row rather than arbitrarily spawn a new one
                         */
                        $this->getLogger()->info(sprintf(
                            'Migrating "%s" to %s roletype',
                            $candidate['title'],
                            self::ROLETYPE_STAFF
                        ));

                        $this->getLogger()->debug(sprintf(
                            'Assigning roletype %s via lookup, using role id %s',
                            self::ROLETYPE_STAFF,
                            $roleId
                        ));
                    } elseif (self::ROLETYPE_PARENT === $roletypeLookup[$roleId]) {
                        /* Create a button in the parent roletype
                         * TODO if there's only one $roleId you could just update
                         *  the existing row rather than arbitrarily spawn a new one
                         */
                        $this->getLogger()->info(sprintf(
                            'Migrating "%s" to %s roletype',
                            $candidate['title'],
                            self::ROLETYPE_PARENT
                        ));

                        $this->getLogger()->debug(sprintf(
                            'Assigning roletype %s via lookup, using role id %s',
                            self::ROLETYPE_PARENT,
                            $roleId
                        ));
                    } else {
                        /* Unsupported roletype; skip it
                         * Not sure how you'd get here, but it might be possible somehow
                         */
                        continue;
                    }
                }
            }

            /* For every role type related to this button, spawn a new one,
             * an exact replica. If the button is associated with only one
             * role type, you can just reuse it instead.
             */

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
     * @return array
     */
    public function generateRoletypeLookup(): array
    {
        $db = $this->getDatabaseInternal();

        $query = "
            SELECT id, name, group_type 
            FROM `groups`
        ";

        $statement = $db->prepare($query);
        $statement->execute();

        $lookup = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $lookup[$row['id']] = $row['group_type'];
        }

        return $lookup;
    }


    /**
     * Generate a list of buttons we need to recreate
     *
     * @return array
     */
    public function generateCandidates(): array
    {
        $query = "
            SELECT 
                pb.*,
                pbra.role_id
            FROM profile_button pb
            INNER JOIN profile_button_role_availability pbra ON pb.id = pbra.profile_button_id
        ";

        $statement = $this->getDatabaseInternal()->prepare($query);
        $statement->execute();

        $candidates = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($candidates[$row['id']])) {
                $data = $row;

                /* We don't actually need this node here
                 */
                unset($data['role_id']);

                $candidates[$row['id']] = $data;

                /* Build a unique list of related role_ids
                 */
                $candidates[$row['id']]['_roles'][$row['role_id']] = $row['role_id'];
            }

            /* Add it to the list uniquely
             */
            $candidates[$row['id']]['_roles'][$row['role_id']] = $row['role_id'];
        }

        return $candidates;
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
