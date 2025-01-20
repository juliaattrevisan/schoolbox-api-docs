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
     * Generate the list
     *
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function getUsersByServiceName(): array
    {
        /* We don't need to list the HOMEPAGE service (1) here
         * The TUTORIAL service (4) got deleted at some point
         */
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

        $usersByServiceName = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usersByServiceName[$row['name']] = (
                $this->userAccessMapper->findUsersForService($row['id'])->getUnique('id')->indexBy('id')
            );
        }

        return $usersByServiceName;
    }
}
