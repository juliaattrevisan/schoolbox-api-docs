<?php

/**
 * Roster
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

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
    public function getServiceNameById()
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
     * Generate the list
     *
     * @param $serviceNameById array<int, string> An array of service names indexed by serviceId.
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \ReflectionException
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
}
