<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

use Alaress\Web\Controller\Base;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var \Schoolbox\Error\Mapper
     */
    private $errorMapper; // TODO: Remove unused mapper.

    /**
     * @var \Schoolbox\UserMapper
     */
    private $userMapper;

    /**
     * @var Access\UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * Controller constructor.
     * @param ErrorMapper      $errorMapper
     * @param UserAccessMapper $userAccessMapper
     * @param UserMapper       $userMapper
     * @param CampusMapper     $campusMapper
     * @param RoleMapper       $roleMapper
     */
    public function __construct(
        // TODO: Use compact constructor format.
        ErrorMapper $errorMapper,
        Access\UserAccessMapper $userAccessMapper,
        UserMapper $userMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper
    )
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
    }

    /**
     * A list of services and the people in them
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getRoster()
    {
        $permissions = new Permissions(
            $this->userMapper,
            $this->userAccessMapper,
            $this->campusMapper,
            $this->roleMapper
        );

        $this->response = $this->responseFromTemplate(
            '@service/permissions.twig',
            ['servicePermissionTablesData' => $permissions->getServicePermissionTablesData()]
        );
    }
}
