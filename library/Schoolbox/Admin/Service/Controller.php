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
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getRoster()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@service/permissions.twig', [
            'services' => (new Permissions($this->userAccessMapper))->generate(),
            'pageCurrent' => 'user',

        ]));
    }
}
