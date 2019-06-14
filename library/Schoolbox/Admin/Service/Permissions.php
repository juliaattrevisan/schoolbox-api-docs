<?php
/**
 * Roster
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Service;

use Schoolbox\Permission\Access;

class Permissions
{

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
    public function generate() : array
    {
        $services = [];

        /* We don't need to list the HOMEPAGE service (1) here
         * The TUTORIAL service (4) got deleted at some point
         */
        $query = '
            SELECT id, name
            FROM lu_service
            WHERE id NOT IN (1, 4)
            ORDER BY name
        ';

        $statement = $this->userAccessMapper->getDatabase()->prepare($query);
        $statement->execute();

        while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $services[$row['name']] = $this->userAccessMapper->findUsersForService($row['id'])->getUnique('id')->indexBy('id');
        }

        return $services;
    }
}
