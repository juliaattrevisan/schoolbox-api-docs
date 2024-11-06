<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli\Strategy;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;

class CustomButtons
{
    /**
     * Corresponds to a secondary identifier in the schema
     * so we can figure out custom buttons from system buttons
     */
    private const CUSTOM = 'custom';


    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $createButtonStatement;
    private \PDOStatement $campusAvailabilityStatement;
    private \PDOStatement $campusAvailabilityInsertStatement;
    private \PDOStatement $campusVisibilityStatement;
    private \PDOStatement $campusVisibilityInsertStatement;
    private \PDOStatement $roleAvailabilityStatement;
    private \PDOStatement $roleAvailabilityInsertStatement;
    private \PDOStatement $roleVisibilityStatement;
    private \PDOStatement $roleVisibilityInsertStatement;


    /**
     * Constructor.
     *
     * @param bool $perform
     * @param IConnector $db
     * @param LoggerInterface $log
     * @param array $roletypeLookup
     */
    public function __construct(
        private bool $perform,
        private IConnector $db,
        private LoggerInterface $log,
        private array $roletypeLookup
    ) {
    }


    /**
     * Rebuild custom buttons
     *
     * @return void
     */
    public function migrate(): void
    {
        /* Set up some prepared statements, presuming that we'll go ahead with all this
         */
        $this->prepareStatements();

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
        foreach ($candidates as $candidate) {
            /* For every role type related to this button, spawn a new one,
             * an exact replica. If the button is associated with only one
             * role type, you can just reuse it instead.
             */
            foreach ($candidate['_roletypes'] as $roletype) {
                /* Create a button in the current roletype
                 */
                $profileButtonId = $this->createButton($candidate, $roletype);

                /* Bring along their existing relationships too, exactly as they were,
                 * and reassign them to this new row we just made
                 */

                /* Campus availability/visibility
                 */
                $this->createCampusAvailability($profileButtonId, (int)$candidate['id']);
                $this->createCampusVisibility($profileButtonId, (int)$candidate['id']);

                /* Role availability/visibility
                 */
                $this->createRoleAvailability($profileButtonId, (int)$candidate['id']);
                $this->createRoleVisibility($profileButtonId, (int)$candidate['id']);
            }
        }

        /* Soft-delete all the old buttons from which we migrated
         */
        $this->cleanup();
    }


    /**
     * We'll be reusing these prepared statements a lot
     *
     * @return void
     */
    private function prepareStatements(): void
    {
        $this->createButtonStatement = $this->db->prepare("
            INSERT INTO profile_button (roletype, type, sort_order, title, text, icon, url, css, target)
            VALUES (:roletype, :type, :sortOrder, :title, :text, :icon, :url, :css, :target)
        ");

        $this->campusAvailabilityStatement = $this->db->prepare("
            SELECT *
            FROM profile_button_campus_availability
            WHERE profile_button_id = :profileButtonId
        ");

        $this->campusAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->campusVisibilityStatement = $this->db->prepare("
            SELECT *
            FROM profile_button_campus_visibility
            WHERE profile_button_id = :profileButtonId
        ");

        $this->campusVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->roleAvailabilityStatement = $this->db->prepare("
            SELECT *
            FROM profile_button_role_availability
            WHERE profile_button_id = :profileButtonId
        ");

        $this->roleAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");

        $this->roleVisibilityStatement = $this->db->prepare("
            SELECT *
            FROM profile_button_role_visibility
            WHERE profile_button_id = :profileButtonId
        ");

        $this->roleVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");
    }


    /**
     * Creates a new button in profile_button, associated with $roletype,
     * returning you its autoincremented id so you can re-establish relationships
     * with this new button
     *
     * @param array $candidate
     * @param string $roletype
     * @return int|null
     */
    private function createButton(array $candidate, string $roletype): ?int
    {
        $this->log->info(sprintf(
            'Migrating "%s" to %s roletype',
            $candidate['title'],
            $roletype
        ));

        if ($this->perform) {
            $this->createButtonStatement->execute([
                'roletype' => $roletype,
                'type' => self::CUSTOM,
                'sortOrder' => $candidate['sort_order'],
                'title' => $candidate['title'],
                'text' => $candidate['text'],
                'icon' => $candidate['icon'],
                'url' => $candidate['url'],
                'css' => $candidate['css'],
                'target' => $candidate['target'],
            ]);

            /* The new button's id - we just made this a moment ago
             */
            return (int)$this->db->lastInsertId();
        }

        return null;
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param int $oldProfileButtonId
     * @return void
     */
    private function createCampusAvailability(?int $profileButtonId, int $oldProfileButtonId): void
    {
        if ($this->perform) {
            if (!$profileButtonId) {
                $this->log->warning(sprintf(
                    'Invalid profile button identifier for old profile button %s; skipping',
                    $oldProfileButtonId
                ));

                return;
            }

            $this->campusAvailabilityStatement->execute([
                'profileButtonId' => $oldProfileButtonId
            ]);

            while ($row = $this->campusAvailabilityStatement->fetch(\PDO::FETCH_ASSOC)) {
                $this->campusAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $row['campus_id']
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param int $oldProfileButtonId
     * @return void
     */
    private function createCampusVisibility(?int $profileButtonId, int $oldProfileButtonId): void
    {
        if ($this->perform) {
            if (!$profileButtonId) {
                $this->log->warning(sprintf(
                    'Invalid profile button identifier for old profile button %s; skipping',
                    $oldProfileButtonId
                ));

                return;
            }

            $this->campusVisibilityStatement->execute([
                'profileButtonId' => $oldProfileButtonId
            ]);

            while ($row = $this->campusVisibilityStatement->fetch(\PDO::FETCH_ASSOC)) {
                $this->campusVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $row['campus_id']
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param int $oldProfileButtonId
     * @return void
     */
    private function createRoleAvailability(?int $profileButtonId, int $oldProfileButtonId): void
    {
        if ($this->perform) {
            if (!$profileButtonId) {
                $this->log->warning(sprintf(
                    'Invalid profile button identifier for old profile button %s; skipping',
                    $oldProfileButtonId
                ));

                return;
            }

            $this->roleAvailabilityStatement->execute([
                'profileButtonId' => $oldProfileButtonId
            ]);

            while ($row = $this->roleAvailabilityStatement->fetch(\PDO::FETCH_ASSOC)) {
                $this->roleAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $row['role_id']
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param int $oldProfileButtonId
     * @return void
     */
    private function createRoleVisibility(?int $profileButtonId, int $oldProfileButtonId): void
    {
        if (!$profileButtonId) {
            $this->log->warning(sprintf(
                'Invalid profile button identifier for old profile button %s',
                $oldProfileButtonId
            ));
        }

        if ($this->perform) {
            $this->roleVisibilityStatement->execute([
                'profileButtonId' => $oldProfileButtonId
            ]);

            while ($row = $this->roleVisibilityStatement->fetch(\PDO::FETCH_ASSOC)) {
                $this->roleVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $row['role_id']
                ]);
            }
        }
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
            WHERE pb.roletype IS NULL
        ";

        $statement = $this->db->prepare($query);
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
            $roletype = $this->roletypeLookup[$row['role_id']];

            /* Add it to the list uniquely
             */
            $candidates[$row['id']]['_roletypes'][$roletype] = $roletype;
        }

        return $candidates;
    }


    /**
     * Soft-delete all the old buttons from which we migrated
     *
     * @return void
     */
    public function cleanup(): void
    {
        $query = "
            UPDATE profile_button
            SET deleted_at = NOW()
            WHERE roletype IS NULL
        ";

        $statement = $this->db->prepare($query);
        $statement->execute();
    }
}
