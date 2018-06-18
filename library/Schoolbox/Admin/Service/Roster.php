<?php
/**
 * Roster
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Service;

use ReflectionClass;
use Schoolbox\Permission\Access;

class Roster
{
    /**
     * Service prefix
     */
    private const PREFIX = 'SERVICE_';

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
     * Make fit for human consumption
     *
     * @param string $name
     * @return string
     */
    private function nice(string $name) : string
    {
        return ucwords(strtolower(str_replace([self::PREFIX, '_'], ['', ' '], $name)));
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
     */
    public function generate() : array
    {
        $services = [];

        $constants = (new ReflectionClass(Access\UserAccess::class))->getConstants();

        ksort($constants);

        foreach ($constants as $constant => $value) {
            if (strpos($constant, self::PREFIX) > -1) {
                if ($value !== Access\UserAccess::SERVICE_HOMEPAGE) {
                    $services[$this->nice($constant)] = $this->userAccessMapper->findUsersForService($value)->getUnique('id')->indexBy('id');
                }
            }
        }

        return $services;
    }

}