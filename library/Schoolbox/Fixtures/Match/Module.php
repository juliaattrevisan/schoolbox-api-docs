<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
 
namespace Schoolbox\Fixtures\Match;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Schoolbox\Module\RestfulModule;
use Schoolbox\Module\StandardModuleTrait;
use \Schoolbox\Permission\Access\UserAccess;

class Module extends RestfulModule
{
    use StandardModuleTrait;

    /**
     * @param ContainerInterface $injector
     * @param RestfulModule      $context
     */
    public function __construct(ContainerInterface $injector, RestfulModule $context)
    {
        parent::__construct($injector, Mapper::class, 'matches', $context, 'sportsfixturesMatch');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

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

}
