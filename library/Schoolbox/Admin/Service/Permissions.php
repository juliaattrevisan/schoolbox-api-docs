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
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Permissions
{
    /**
     * This var is no longer in existence but it's in the lookup
     */
    private const SERVICE_TUTORIAL = 4;

    /**
     * Constructor.
     * @param UserMapper $userMapper
     * @param Access\UserAccessMapper $userAccessMapper
     * @param CampusMapper $campusMapper
     * @param RoleMapper $roleMapper
     */
    public function __construct(
        private readonly UserMapper $userMapper,
        private readonly Access\UserAccessMapper $userAccessMapper,
        private readonly CampusMapper $campusMapper,
        private readonly RoleMapper $roleMapper
    )
    {
    }

    /**
     * Return an array of service names indexed by serviceId.
     * The services returned are those that should be listed on the 'Admin > Service Permissions' page.
     * @return array<int, string>
     */
    public function getServiceNameById(): array
    {
        return $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT id, name
            FROM lu_service
            WHERE id NOT IN (:homepageConst, :tutorialConst)
            ORDER BY name',
            [
                // Exclude SERVICE_HOMEPAGE because it is special in that userAccess rows for SERVICE_HOMEPAGE represent
                // group permissions, not service permissions (see User/Controller->getModify()).
                'homepageConst' => UserAccess::SERVICE_HOMEPAGE,
                // Exclude SERVICE_TUTORIAL because it is no longer used in code (but still exists in the database).
                'tutorialConst' => self::SERVICE_TUTORIAL
            ]
        )->fetchColumnArray('name', 'id');
    }

    /**
     * @return array<string, list<array{user: User, accessName: string, sidLabel: string, sidDisplayValue: string}>>
     */
    public function getServicePermissionTablesData(): array
    {
        // TODO: Get services using mapper instead of above function.
        $serviceNameLookup = $this->getServiceNameById();

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
            ORDER BY lus.name ASC, u.firstname ASC, u.lastname ASC'
        )->fetchAll();

        $userIds = array_unique(array_column($userAccessRows, 'userId'));
        $users = $this->userMapper->findByColumn('id', $userIds);
        $campuses = $this->campusMapper->findAll();
        $roles = $this->roleMapper->findAll();

        $servicePermissionTablesData = [];

        foreach ($userAccessRows as $row) {
            $serviceName = $serviceNameLookup[$row['serviceId']];

            if (!array_key_exists($serviceName, $servicePermissionTablesData)) {
                $servicePermissionTablesData[$serviceName] = [];
            }

            $servicePermissionTablesData[$serviceName][] = [
                'user' => $users->getBy('id', $row['userId']),
                'accessName' => ucfirst(UserAccess::ACCESS_LEVEL_TO_NAME[$row['accessLevel']]), // TODO: Change ACCESS_LEVEL_TO_NAME so that 'Moderator' is returned instead of 'Admin'.
                'sidLabel' => match ($serviceName) {
                    'Calendar' => 'Campus',
                    'Impersonator' => 'Role',
                    default => ''
                },
                'sidDisplayValue' => match ($serviceName) {
                    'Calendar' => $campuses->getBy('id', $row['sid'])->getName(),
                    'Impersonator' => $roles->getBy('id', $row['sid'])->getName(),
                    default => ''
                },
            ];
        }

        return $servicePermissionTablesData;
    }
}
