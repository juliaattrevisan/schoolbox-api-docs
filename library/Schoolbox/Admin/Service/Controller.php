<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

use Alaress\Web\Controller\Base;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     * @param UserAccessMapper $userAccessMapper
     * @param UserMapper       $userMapper
     * @param CampusMapper     $campusMapper
     * @param RoleMapper       $roleMapper
     */
    public function __construct(
        private readonly Access\UserAccessMapper $userAccessMapper,
        private readonly UserMapper $userMapper,
        private readonly CampusMapper $campusMapper,
        private readonly RoleMapper $roleMapper
    ) {
    }

    /**
     * A list of services and the people in them
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws Error
     */
    public function getRoster(): void
    {
        $permissions = new Permissions(
            $this->userMapper,
            $this->userAccessMapper,
            $this->campusMapper,
            $this->roleMapper
        );

        $this->response = $this->responseFromTemplate(
            '@service/permissions.twig',
            ['servicePermissionTablesData' => $permissions->getReportTablesData()]
        );
    }
}
