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


    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $campusAvailabilityStatement;
    private \PDOStatement $campusAvailabilityInsertStatement;
    private \PDOStatement $campusVisibilityStatement;
    private \PDOStatement $campusVisibilityInsertStatement;
    private \PDOStatement $roleAvailabilityStatement;
    private \PDOStatement $roleAvailabilityInsertStatement;
    private \PDOStatement $roleVisibilityStatement;
    private \PDOStatement $roleVisibilityInsertStatement;


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

            /* Set up some prepared statements, presuming that we'll go ahead with all this
             */
            $this->prepareStatements();

            /* Figure out which roletype corresponds to each role
             */
            $roletypeLookup = $this->generateRoletypeLookup();

            /* Get a list of buttons to inspect - users can specify --system,
             * --custom, or both
             */
            $candidates = $this->generateCandidates($roletypeLookup);

            /* Each button can have zero or more roles associated with it
             * Inspect each button's relationships to find out which roles,
             * and thus which role types, they need. Find a unique list of
             * roletypes; we need to clone thus button out for each one.
             *
             * We'll use role visibility as the most definitive way to figure
             * out corresponding roletype(s) for a button. Campus is too broad
             * a stroke and roles have a direct relationship with a roletype.
             */
            foreach ($candidates as $candidate) {
                foreach ($candidate['_roletypes'] as $roletype) {
                    /* Create a button in the current roletype
                     * TODO if there's only one $roleId you could just update
                     *  the existing row rather than arbitrarily spawn a new one
                     */
                    $profileButtonId = $this->createButton($candidate, $roletype, self::OPT_LONG_TYPE_CUSTOM);

                    /* Bring along their existing relationships too, exactly as they were,
                     * and reassign them to this new row we just made
                     */

                    /* Campus availability
                     */
                    $this->createCampusAvailability($profileButtonId, $candidate);

                    /* Campus visibility
                     */
                    $this->createCampusVisibility($profileButtonId, $candidate);

                    /* Role availability
                     */
                    $this->createRoleAvailability($profileButtonId, $candidate);

                    /* Role visibility
                     */
                    $this->createRoleVisibility($profileButtonId, $candidate);
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
     * We'll be reusing these prepared statements a lot
     *
     * @return void
     */
    private function prepareStatements(): void
    {
        $this->campusAvailabilityStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM profile_button_campus_availability
            WHERE profile_button_id = :profileButtonId
        ");

        $this->campusAvailabilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (?, ?)
        ");

        $this->campusVisibilityStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM profile_button_campus_visibility
            WHERE profile_button_id = :profileButtonId
        ");

        $this->campusVisibilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (?, ?)
        ");

        $this->roleAvailabilityStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM profile_button_role_availability
            WHERE profile_button_id = :profileButtonId
        ");

        $this->roleAvailabilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (?, ?)
        ");

        $this->roleVisibilityStatement = $this->getDatabaseInternal()->prepare("
            SELECT *
            FROM profile_button_role_availability
            WHERE profile_button_id = :profileButtonId
        ");

        $this->roleVisibilityInsertStatement = $this->getDatabaseInternal()->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (?, ?)
        ");
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
     * @param array $roletypeLookup
     * @return array
     */
    public function generateCandidates(array $roletypeLookup): array
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
                $candidates[$row['id']]['_roletypes'] = [];
            }

            /* Identify the roletype
             */
            $roletype = $roletypeLookup[$row['role_id']];

            /* Add it to the list uniquely
             */
            $candidates[$row['id']]['_roletypes'][$roletype] = $roletype;
        }

        return $candidates;
    }


    /**
     * Creates a new button in profile_button, associated with $roletype,
     * returning you its autoincremented id so you can re-establish relationships
     * with this new button
     *
     * @param array $candidate
     * @param string $roletype
     * @param string $type
     * @return int
     */
    private function createButton(array $candidate, string $roletype, string $type): int {
        $this->getLogger()->info(sprintf(
            'Migrating "%s" to %s roletype',
            $candidate['title'],
            self::ROLETYPE_STUDENT
        ));

        $query = "
            INSERT INTO profile_button (sort_order, title, text, icon, url, css, target, type, roletype)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $statement = $this->getDatabaseInternal()->prepare($query);
        $statement->execute([
            $candidate['sort_order'],
            $candidate['title'],
            $candidate['text'],
            $candidate['icon'],
            $candidate['url'],
            $candidate['css'],
            $candidate['target'],
            $type,
            $roletype
        ]);

        /* The new button's id - we just made this a moment ago
         */
        return (int)$this->getDatabaseInternal()->lastInsertId();
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int $profileButtonId
     * @param array $candidate
     * @return void
     */
    private function createCampusAvailability(int $profileButtonId, array $candidate): void
    {
        $this->campusAvailabilityStatement->execute([
            'profileButtonId' => $candidate['id']
        ]);

        while ($row = $this->campusAvailabilityStatement->fetch(\PDO::FETCH_ASSOC)) {
            $this->campusAvailabilityInsertStatement->execute([$profileButtonId, $row['campus_id']]);
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int $profileButtonId
     * @param array $candidate
     * @return void
     */
    private function createCampusVisibility(int $profileButtonId, array $candidate): void
    {
        $this->campusVisibilityStatement->execute([
            'profileButtonId' => $candidate['id']
        ]);

        while ($row = $this->campusVisibilityStatement->fetch(\PDO::FETCH_ASSOC)) {
            $this->campusVisibilityInsertStatement->execute([$profileButtonId, $row['campus_id']]);
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int $profileButtonId
     * @param array $candidate
     * @return void
     */
    private function createRoleAvailability(int $profileButtonId, array $candidate): void
    {
        $this->roleAvailabilityStatement->execute([
            'profileButtonId' => $candidate['id']
        ]);

        while ($row = $this->roleAvailabilityStatement->fetch(\PDO::FETCH_ASSOC)) {
            $this->roleAvailabilityInsertStatement->execute([$profileButtonId, $row['role_id']]);
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int $profileButtonId
     * @param array $candidate
     * @return void
     */
    private function createRoleVisibility(int $profileButtonId, array $candidate): void
    {
        $this->roleVisibilityStatement->execute([
            'profileButtonId' => $candidate['id']
        ]);

        while ($row = $this->roleVisibilityStatement->fetch(\PDO::FETCH_ASSOC)) {
            $this->roleVisibilityInsertStatement->execute([$profileButtonId, $row['role_id']]);
        }
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
