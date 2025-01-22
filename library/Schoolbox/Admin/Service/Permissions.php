<?php

/**
 * Roster
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

use Schoolbox\Collection;
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\UserAccess;

class Permissions
{
    /**
     * This var is no longer in existence but it's in the lookup
     */
    private const SERVICE_TUTORIAL = 4;


    /**
     * @var Access\UserAccessMapper
     */
    private $userAccessMapper;


    /**
     * Roster constructor.
     * @param Access\UserAccessMapper $userAccessMapper
     */
    public function __construct(Access\UserAccessMapper $userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * Return an array of service names indexed by serviceId.
     * The services returned are those that should be listed on the 'Admin > Service Permissions' page.
     * @return array<int, string>
     */
    public function getServiceNameById(): array
    {
        /* We don't need to list the HOMEPAGE service (1) here
         * The TUTORIAL service (4) got deleted at some point
         */
        return $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT id, name
            FROM lu_service
            WHERE id NOT IN (:homepageConst, :tutorialConst)
            ORDER BY name',
            [
                'homepageConst' => UserAccess::SERVICE_HOMEPAGE,
                'tutorialConst' => self::SERVICE_TUTORIAL
            ]
        )->fetchColumnArray('name', 'id');
    }


    /**
     * Return an array of (Collection of User objects), indexed by service name.
     *
     * @param $serviceNameById array<int, string> An array of service names indexed by serviceId.
     * @return array<string, Collection>
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function getUsersByServiceName(array $serviceNameById): array
    {
        $usersByServiceName = [];

        foreach ($serviceNameById as $serviceId => $serviceName) {
            $usersByServiceName[$serviceName] = (
                $this->userAccessMapper->findUsersForService($serviceId)->getUnique('id')->indexBy('id')
            );
        }

        return $usersByServiceName;
    }


    /**
     * Return an array containing information from the userAccess table, indexed by userId.
     *
     * @param array<int, string> $serviceNameById
     * @return array<int, array<string, list<array{accessType: string, sidLabel: string, sidDisplayValue: string}>>>
     */
    public function getUserAccessInfoByUserId(array $serviceNameById): array
    {
        $userAccessRows = $this->userAccessMapper->getDatabase()->executeQuery(
            // Note accessTypeNames in the ua.type lookup below come from Permission/Access/README.md
            'SELECT
            ua.service AS serviceId,
            ua.sid,
            lus.name AS serviceName,
            ua.uid AS userId,
            (
                CASE ua.type
                    WHEN 0 THEN "None"
                    WHEN 1 THEN "Read"
                    WHEN 2 THEN "Write"
                    WHEN 3 THEN "Moderate"
                END
            ) AS accessType
            FROM userAccess ua
            JOIN lu_service lus ON ua.service = lus.id
            JOIN user u ON ua.uid = u.id
            WHERE u.deleted_at IS NULL
            AND lus.id IN (' . implode(',', array_map('intval', array_keys($serviceNameById))) . ')'
        )->fetchAll();

        $roleNameById = $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT id, name
            FROM groups
            WHERE deleted_at IS NULL'
        )->fetchColumnArray('name', 'id');

        $campusNameById = $this->userAccessMapper->getDatabase()->executeQuery(
            'SELECT id, name
            FROM luCampus
            WHERE deleted_at IS NULL'
        )->fetchColumnArray('name', 'id');

        $uaStructsByServiceNameByUserId = [];

        foreach ($userAccessRows as $row) {
            $userId = (int)$row['userId'];
            $serviceName = $row['serviceName'];

            if (!array_key_exists($userId, $uaStructsByServiceNameByUserId)) {
                $uaStructsByServiceNameByUserId[$userId] = [];
            }

            if (!array_key_exists($serviceName, $uaStructsByServiceNameByUserId[$userId])) {
                $uaStructsByServiceNameByUserId[$userId][$serviceName] = [];
            }

            $uaStructsByServiceNameByUserId[$userId][$serviceName][] = [
                'accessType' => $row['accessType'],
                'sidLabel' => match ($serviceName) {
                    'Calendar' => 'Campus',
                    'Impersonator' => 'Role',
                    default => null
                },
                'sidDisplayValue' => match ($serviceName) {
                    'Calendar' => $campusNameById[$row['sid']] ?? '',
                    'Impersonator' => $roleNameById[$row['sid']] ?? '',
                    default => null
                },
            ];
        }

        return $uaStructsByServiceNameByUserId;
    }
}
