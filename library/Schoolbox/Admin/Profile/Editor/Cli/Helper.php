<?php

namespace Schoolbox\Admin\Profile\Editor\Cli;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;

class Helper
{

    /* Some prepared statements that we'll often reuse
     */
    private \PDOStatement $campusAvailabilityInsertStatement;
    private \PDOStatement $campusVisibilityInsertStatement;
    private \PDOStatement $roleAvailabilityInsertStatement;
    private \PDOStatement $roleVisibilityInsertStatement;


    /**
     * Constructor.
     *
     * @param IConnector $db
     * @param bool       $perform
     */
    public function __construct(private IConnector $db, private bool $perform) {
        $this->prepare();
    }


    /**
     * Set up some prepared statements
     *
     * @return void
     */
    private function prepare(): void
    {
        $this->campusAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_availability (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->campusVisibilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_campus_visibility (profile_button_id, campus_id)
            VALUES (:profileButtonId, :campusId)
        ");

        $this->roleAvailabilityInsertStatement = $this->db->prepare("
            INSERT INTO profile_button_role_availability (profile_button_id, role_id)
            VALUES (:profileButtonId, :roleId)
        ");

        $this->roleVisibilityInsertStatement = $this->db->prepare("
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


    /**
     * Recreate a relationship using $candidate's existing nodes and this new $profileButtonId
     *
     * @param int|null $profileButtonId
     * @param array $campusIds
     * @return void
     */
    public function createCampusAvailability(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                $this->campusAvailabilityInsertStatement->execute([
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
    public function createCampusVisibility(?int $profileButtonId, array $campusIds): void
    {
        if ($this->perform) {
            foreach ($campusIds as $campusId) {
                $this->campusVisibilityInsertStatement->execute([
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
    public function createRoleAvailability(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                $this->roleAvailabilityInsertStatement->execute([
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
    public function createRoleVisibility(?int $profileButtonId, array $roleIds): void
    {
        if ($this->perform) {
            foreach ($roleIds as $roleId) {
                $this->roleVisibilityInsertStatement->execute([
                    'profileButtonId' => $profileButtonId,
                    'roleId' => $roleId
                ]);
            }
        }
    }
}
