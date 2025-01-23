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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
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
     * @param UserAccessMapper $userAccessMapper
     * @param CampusMapper $campusMapper
     * @param RoleMapper $roleMapper
     */
    public function __construct(
        private readonly UserMapper $userMapper,
        private readonly UserAccessMapper $userAccessMapper,
        private readonly CampusMapper $campusMapper,
        private readonly RoleMapper $roleMapper
    ) {
    }

    /**
     * Generate the list
     *
     * @return array
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
            $services[$row['name']] = $this->userAccessMapper->findUsersForService($row['id'])->getUnique('id')->indexBy('id');
        }

        return $services;
    }

    /**
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

        $userAccessObjects = $this->userAccessMapper->findByColumn('service', array_keys($serviceNameLookup));
        $usersWithDuplicates = $userAccessObjects->map(function ($uaObj) {return $uaObj->getUser();});
        $userIds = array_unique($usersWithDuplicates->getPropertyValues('id'));
        $users = $this->userMapper->findByColumn('id', $userIds);
        $campuses = $this->campusMapper->findAll();
        $roles = $this->roleMapper->findAll();

        $userAccessObjects->sortByCallback(
            function (UserAccess $a, UserAccess $b) use ($serviceNameLookup, $users, $campuses, $roles) {
                $serviceNameA = $serviceNameLookup[$a->getService()];
                $serviceNameB = $serviceNameLookup[$b->getService()];
                $userA = $users->getBy('id', $a->getUser()->getId());
                $userB = $users->getBy('id', $b->getUser()->getId());

                // ORDER BY serviceName ASC, fullName ASC, accessLevel DESC, serviceIdentifier ASC.
                if ($serviceNameA === $serviceNameB) {
                    if ($userA->getFullName() === $userB->getFullName()) {
                        if ($a->getAccessLevel() === $b->getAccessLevel()) {
                            return $a->getServiceIdentifier() <=> $b->getServiceIdentifier();
                        }
                        return $b->getAccessLevel() <=> $a->getAccessLevel();
                    }
                    return $userA->getFullName() <=> $userB->getFullName();
                }
                return strtolower($serviceNameA) <=> strtolower($serviceNameB); // String to lower to mimic SQL ORDER BY behaviour.
            }
        );

        $servicePermissionTablesData = [];

        // Use 'moderate' for full access instead of UserAccess::MEMBERSHIP_ADMIN.
        // This is to ensure service permission terminology matches that used on the Modify User page.
        // TODO: Fix the UserAccess constants so this will not be necessary.
        $accessLevelToNameModified = UserAccess::ACCESS_LEVEL_TO_NAME;
        $accessLevelToNameModified[UserAccess::ACCESS_FULL] = 'moderate';

        foreach ($userAccessObjects as $uaObj) {
            $serviceName = $serviceNameLookup[$uaObj->getService()];

            if (!array_key_exists($serviceName, $servicePermissionTablesData)) {
                $servicePermissionTablesData[$serviceName] = [];
            }

            $servicePermissionTablesData[$serviceName][] = [
                'user' => $users->getBy('id', $uaObj->getUser()->getId()), // Note $uaObj->user is not hydrated.
                'accessName' => ucfirst($accessLevelToNameModified[$uaObj->getAccessLevel()]),
                'sidLabel' => match ($serviceName) {
                    'Calendar' => 'Campus',
                    'Impersonator' => 'Role',
                    default => ''
                },
                'sidDisplayValue' => match ($serviceName) {
                    'Calendar' => $campuses->getBy('id', $uaObj->getServiceIdentifier())->getName(),
                    'Impersonator' => $roles->getBy('id', $uaObj->getServiceIdentifier())->getName(),
                    default => ''
                },
            ];
        }

        return $servicePermissionTablesData;
    }
}
