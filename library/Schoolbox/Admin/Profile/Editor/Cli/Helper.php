<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Psr\Log\LoggerInterface;

class Helper
{
    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $fieldCampusAvailabilityInsertStatement;
    private \PDOStatement $fieldRoletypeVisibilityInsertStatement;
    private \PDOStatement $buttonCampusAvailabilityInsertStatement;
    private \PDOStatement $buttonCampusVisibilityInsertStatement;
    private \PDOStatement $buttonRoleAvailabilityInsertStatement;
    private \PDOStatement $buttonRoleVisibilityInsertStatement;


    /**
     * Constructor.
     *
     * @param IConnector $db
     * @param bool $perform
     * @param LoggerInterface $logger
     */
    public function __construct(private IConnector $db, private bool $perform, private LoggerInterface $logger)
    {
        $this->prepare();
    }


    /**
     * Set up some prepared statements
     *
     * @return void
     */
    private function prepare(): void
    {
        /* Fields
         */
        $this->fieldCampusAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_field_campus_availability (profile_field_id, campus_id)
            VALUES (:profileFieldId, :campusId)
        ");

        $this->fieldRoletypeVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_field_roletype_visibility (profile_field_id, roletype)
            VALUES (:profileFieldId, :roletype)
        ");

        /* Buttons
         */
        $this->buttonCampusAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->buttonCampusVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->buttonRoleAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");

        $this->buttonRoleVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_visibility (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");
    }


    /**
     * Fetch primary ids for roles, returning them in a stack,
     * corresponding to the roletypes specified in $roleType
     *
     * @param array $roleType
     * @return array
     */
    public function fetchRoleIds(array $roleType): array
    {
        $ids = [];

        $placeholders = Builder::getArrayPlaceholders($roleType, 'roletype');
        $params = Builder::getArrayParameters($roleType, 'roletype');

        $statement = $this->db->prepare("
            SELECT id
            FROM groups
            WHERE group_type IN {$placeholders}
        ");

        $statement->execute($params);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }


    /**
     * Fetch primary ids for campuses, returning them in a stack
     *
     * @return array
     */
    public function fetchCampusIds(): array
    {
        $ids = [];

        $statement = $this->db->prepare('SELECT id FROM luCampus');
        $statement->execute();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }


    /* Fields
     */

    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileFieldId
     *
     * @param int|null $profileFieldId
     * @param array $campusIds
     * @return void
     */
    public function createFieldCampusAvailability(?int $profileFieldId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                $this->fieldCampusAvailabilityInsertStatement->execute([
                    'profileFieldId' => $profileFieldId,
                    'campusId' => $campusId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileFieldId
     *
     * @param int|null $profileFieldId
     * @param array $roletypes
     * @return void
     */
    public function createFieldRoletypeVisibility(?int $profileFieldId, array $roletypes): void
    {
        if ($this->perform) {
            foreach ($roletypes as $roletype) {
                $this->fieldRoletypeVisibilityInsertStatement->execute([
                    'profileFieldId' => $profileFieldId,
                    'roletype' => $roletype
                ]);
            }
        }
    }


    /* Buttons
     */

    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $campusIds
     * @return void
     */
    public function createButtonCampusAvailability(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                $this->buttonCampusAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $campusId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $campusIds
     * @return void
     */
    public function createButtonCampusVisibility(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                $this->buttonCampusVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'campusId' => $campusId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $roleIds
     * @return void
     */
    public function createButtonRoleAvailability(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                $this->buttonRoleAvailabilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $roleId
                ]);
            }
        }
    }


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $roleIds
     * @return void
     */
    public function createButtonRoleVisibility(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                $this->buttonRoleVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $roleId
                ]);
            }
        }
    }

    /**
     * Update configuration for Studies Code on Student profile screen
     *
     * @param string $roletype
     * @param string $field
     * @param array $campusIds
     * @param array $roletypes
     * @return void
     */
    public function updateField(string $roletype, string $field, array $campusIds, array $roletypes): void
    {
        /* Preset availability and visibility requirements
         */
        $statement = $this->db->prepare("
            SELECT id
            FROM profile_field
            WHERE roletype = :roletype
            AND field = :field
        ");

        $statement->execute([
            'roletype' => $roletype,
            'field' => $field
        ]);

        $profileFieldId = null;

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $profileFieldId = $row['id'];
        }

        /* Un-assign relationships
         */
        $tables = [
            'profile_field_campus_availability',
            'profile_field_roletype_visibility',
        ];

        foreach ($tables as $table) {
            $this->logger->info(sprintf(
                'Removing relationships with %s field in %s',
                $field,
                $table
            ));

            $this->db->executeQuery(sprintf("
                DELETE FROM %s
                WHERE profile_field_id = (SELECT id
                FROM profile_field
                WHERE field = :field
                AND roletype = :roletype
                LIMIT 1)
            ", $table), [
                'field' => $field,
                'roletype' => $roletype,
            ]);
        }

        /* Assign relationships
         */
        $assignedCampusIds = implode(',', $campusIds);

        $this->logger->info(sprintf(
            'Campus availability: assigning campus ids %s to profile field %s (%s)',
            '' === $assignedCampusIds ? '(none)' : $assignedCampusIds,
            $field,
            $profileFieldId
        ));

        $this->createFieldCampusAvailability($profileFieldId, $campusIds);

        $assignedRoletypes = implode(',', $roletypes);

        $this->logger->info(sprintf(
            'Roletype visibility: assigning roletypes %s to profile field %s (%s)',
            '' === $assignedRoletypes ? '(none)' : $assignedRoletypes,
            $field,
            $profileFieldId
        ));

        $this->createFieldRoletypeVisibility($profileFieldId, $roletypes);
    }
}
