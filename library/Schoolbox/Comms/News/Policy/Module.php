<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Policy;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;


class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsPolicy';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes()
    {
        /* Any staff can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser()
                //new Rule\HasRole(Role::STAFF),
                //new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL)
            )
        );

        /* Only superusers or comms officers can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser()
                //new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('GET', '', 'getPolicies', $getPerms);
        $this->register('POST', '', 'postPolicies', $postPerms);
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
            'policyMapper' => $this->getMapperFactory()->getLazy(Mapper::class),
            'topicMapper' => $this->getMapperFactory()->getLazy(TopicMapper::class)
        ];
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
