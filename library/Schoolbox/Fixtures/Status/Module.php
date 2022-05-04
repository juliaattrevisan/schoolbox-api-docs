<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Status;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Psr\Container\ContainerInterface;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Mapper\Exception as MapperException;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @var string
     */
    protected $basename = 'sportsfixturesStatus';

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule|null $context
     */
    public function __construct(ContainerInterface $injector, ?RestfulModule $context)
    {
        parent::__construct($injector, Mapper::class, 'statuses', $context);
    }

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     * @throws \InvalidArgumentException
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        /* Permissions requirements for being a Sports Administrator
         */
        $isSportsAdministrator = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_SPORTS_ADMINISTRATOR, UserAccess::ACCESS_WRITE)
            )
        );

        /* Apply route permissions here as you add them to initRoutes()
         * Note that if the perms change you need to log out/log in to refresh
         */
        $this->addRoutePermission($authorizer, '.*', $isSportsAdministrator);
    }

    /**
     * Specifically for this module, catch exceptions and hide them
     *
     * @param int $id
     * @return object
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    protected function findEntityById($id)
    {
        return $this->getMapper()->findById($id);
    }
}
