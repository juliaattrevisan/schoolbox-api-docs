<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Service;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access;

class Controller extends Base
{
    /**
     * @var \Schoolbox\Error\Mapper
     */
    private $errorMapper;

    /**
     * @var Access\UserAccessMapper
     */
    private $userAccessMapper;


    /**
     * Controller constructor.
     * @param ErrorMapper             $errorMapper
     * @param Access\UserAccessMapper $userAccessMapper
     */
    public function __construct(ErrorMapper $errorMapper, Access\UserAccessMapper $userAccessMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userAccessMapper = $userAccessMapper;
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
        $permissions = new Permissions($this->userAccessMapper);
        $serviceNameById = $permissions->getServiceNameById();

        $this->response = $this->responseFromTemplate('@service/permissions.twig', [
            'usersByServiceName' => $permissions->getUsersByServiceName($serviceNameById),
            'accessTypeByServiceNameByUserId' => $permissions->getAccessTypeByServiceNameByUserId($serviceNameById),
        ]);
    }
}
