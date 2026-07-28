<?php

/**
 * Permissions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Builds the service-permission summaries used by the user administration pages.
 *
 * Services belonging to disabled modules are excluded from the generated display
 * data without deleting or modifying their stored userAccess assignments.
 */
class Permissions
{
    /**
     * This var is no longer in existence but it's in the lookup
     */
    private const SERVICE_TUTORIAL = 4;

    /**
     * Configure the permission builder and module-specific service visibility.
     *
     * @param UserMapper $userMapper Loads users referenced by service-permission assignments.
     * @param UserAccessMapper $userAccessMapper Loads service definitions and their assigned users.
     * @param CampusMapper $campusMapper Resolves campus identifiers displayed in the detailed report.
     * @param RoleMapper $roleMapper Resolves role identifiers displayed in the detailed report.
     * @param IConfigLoader $configLoader Loads configuration settings for the instance.
     */
    public function __construct(
        private readonly UserMapper $userMapper,
        private readonly UserAccessMapper $userAccessMapper,
        private readonly CampusMapper $campusMapper,
        private readonly RoleMapper $roleMapper,
        private readonly IConfigLoader $configLoader
    ) {
    }

    /**
     * Generate the service summary displayed on the user administration page.
     *
     * The returned array is keyed by service name. Services belonging to disabled
     * modules are omitted before their assigned users are loaded.
     *
     * @return array<string, \Schoolbox\Collection>
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function generate(): array
    {
        $services = [];

        // Exclude SERVICE_HOMEPAGE because it is special in that userAccess rows for SERVICE_HOMEPAGE
        // represent group permissions, not service permissions (see User/Controller->getModify()).
        // Exclude SERVICE_TUTORIAL because it is no longer used in code (but still exists in the database).
        $query = '
            SELECT id, name
            FROM lu_service
            WHERE id NOT IN (:homepageConst, :tutorialConst)
            ORDER BY name
        ';

        $statement = $this->userAccessMapper->getDatabase()->prepare($query);
        $statement->execute([
            'homepageConst' => UserAccess::SERVICE_HOMEPAGE,
            'tutorialConst' => self::SERVICE_TUTORIAL
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!$this->isServiceVisible((int)$row['id'])) {
                continue;
            }

            $services[$row['name']] = $this->userAccessMapper->findUsersForService($row['id'])->getUnique('id')->indexBy('id');
        }

        return $services;
    }

    /**
     * Build the table data displayed by the detailed service-permissions report.
     *
     * Unavailable services are removed from the lookup before userAccess rows are
     * queried. This hides existing IB Coordinator assignments while IB is disabled
     * without deleting them, allowing them to reappear when IB is enabled again.
     *
     * @return array<string, list<array{user: User, accessName: string, sidLabel: string, sidDisplayValue: string}>>
     */
    public function getReportTablesData(): array
    {
        // Note there is no Service/Mapper.  Even if there was, mappers don't have a convenient way to get all records except two.
        $serviceNameLookup = $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT id, name
            FROM lu_service
            WHERE id NOT IN (:homepageConst, :tutorialConst)
            ORDER BY name',
            [
                // Exclude SERVICE_HOMEPAGE because it is special in that userAccess rows for SERVICE_HOMEPAGE
                // represent group permissions, not service permissions (see User/Controller->getModify()).
                'homepageConst' => UserAccess::SERVICE_HOMEPAGE,
                // Exclude SERVICE_TUTORIAL because it is no longer used in code (but still exists in the database).
                'tutorialConst' => self::SERVICE_TUTORIAL
            ]
        )->fetchColumnArray('name', 'id');

        $serviceNameLookup = array_filter(
            $serviceNameLookup,
            fn (int $serviceId): bool => $this->isServiceVisible($serviceId),
            ARRAY_FILTER_USE_KEY
        );

        $userAccessRows = $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT
            ua.service AS serviceId,
            ua.sid,
            ua.uid AS userId,
            ua.type AS accessLevel
            FROM userAccess ua
            JOIN user u ON ua.uid = u.id
            JOIN lu_service AS lus ON ua.service = lus.id
            WHERE u.deleted_at IS NULL
            AND ua.service IN (' . implode(',', array_map('intval', array_keys($serviceNameLookup))) . ')
            ORDER BY lus.name ASC, u.firstname ASC, u.lastname ASC, accessLevel DESC, sid ASC'
            // Order by sid ensures ordering has no ambiguity, so ordering is consistent on every page load.
        )->fetchAll();

        $userIds = array_unique(array_column($userAccessRows, 'userId'));
        $users = $this->userMapper->findByColumn('id', $userIds);
        $campuses = $this->campusMapper->findAll();
        $roles = $this->roleMapper->findAll();

        $servicePermissionTablesData = [];

        // Use 'moderate' for full access instead of UserAccess::MEMBERSHIP_ADMIN.
        // This is to ensure service permission terminology matches that used on the Modify User page.
        // TODO: Fix the UserAccess constants so this will not be necessary.
        $accessLevelToNameModified = UserAccess::ACCESS_LEVEL_TO_NAME;
        $accessLevelToNameModified[UserAccess::ACCESS_FULL] = 'moderate';

        foreach ($userAccessRows as $row) {
            $sidLabel = '';
            $sidDisplayValue = '';
            switch ($row['serviceId']) {
                case UserAccess::SERVICE_CALENDAR:
                case UserAccess::SERVICE_DIFFERENTIATION:
                    $campus = $campuses->getBy('id', $row['sid']);
                    if ($campus === null) {
                        // Campus $row['sid'] does not exist or has been deleted, so ignore this userAccess row.
                        continue (2);
                    }
                    $sidLabel = 'Campus';
                    $sidDisplayValue = $campus->getName();
                    break;
                case UserAccess::SERVICE_IMPERSONATOR:
                    $role = $roles->getBy('id', $row['sid']);
                    if ($role === null) {
                        // Role $row['sid'] does not exist or has been deleted, so ignore this userAccess row.
                        continue (2);
                    }
                    $sidLabel = 'Role';
                    $sidDisplayValue = $role->getName();
                    break;
                default:
                    // Leave $sidLabel and $sidDisplayValue as blank.
            }

            $serviceName = $serviceNameLookup[$row['serviceId']];

            if (!array_key_exists($serviceName, $servicePermissionTablesData)) {
                $servicePermissionTablesData[$serviceName] = [];
            }

            $servicePermissionTablesData[$serviceName][] = [
                'user' => $users->getBy('id', $row['userId']),
                'accessName' => ucfirst($accessLevelToNameModified[$row['accessLevel']]),
                'sidLabel' => $sidLabel,
                'sidDisplayValue' => $sidDisplayValue
            ];
        }

        return $servicePermissionTablesData;
    }

    /**
     * Determine whether a service should appear in service-permission listings.
     *
     * @param int $serviceId One of the UserAccess::SERVICE_* constants.
     * @return bool True when the service should be visible.
     */
    private function isServiceVisible(int $serviceId): bool
    {
        /* IB Coordinator follows the IB module feature flag.
         */
        return $serviceId !== UserAccess::SERVICE_IB_COORDINATOR || (bool)$this->configLoader->get(ConfigMapper::NAME_IB_ENABLED);
    }
}
