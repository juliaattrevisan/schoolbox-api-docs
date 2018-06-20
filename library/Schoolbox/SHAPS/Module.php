<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\SHAPS;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Mapper;
use Schoolbox\SHAPS\Auth\Parameters;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;


class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'shaps';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        /* Only superusers are allowed to access SHAPS in any capacity */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );
        
        $this->register('GET', '/provisioning', 'getAll', $getPerms);
        $this->register('GET', '/provisioning/provisioned', 'getProvisioned', $getPerms);
        $this->register('GET', '/provisioning/unprovisioned', 'getUnprovisioned', $getPerms);
        $this->register('GET', '/provisioning/search', 'getUserProvisioningSearch', $getPerms);

        $this->register('GET', '/courses', 'getUsersInCourses', $getPerms);
        $this->register('GET', '/courses/search', 'getUserEnrolmentSearch', $getPerms);
        $this->register('GET', '/courses/{userId}', 'searchCoursesForUser', $getPerms);


        $this->register('GET', '/provisioning/{userId}', 'searchUsers', $getPerms);
        $this->register('GET', '/provisioning/provisionedUsers', 'getProvisionedUsersJSON', $getPerms);


        // Separated so as not to cause merge conflicts
        $this->register('POST', '/provision', 'postProvision', $postPerms);
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
            'userMapper'   => $this->getMapperFactory()->getLazy(UserMapper::class),
            'configLoader' => $this->getConfig(),
            'authParams'   => $injector->lazyNew(
                Parameters::class,
                [
                    'url'         => $this->getConfig()->get('schoolbox_help_url'),
                    'schoolCode'  => $this->getConfig()->get('site_school_code'),
                    'accessToken' => $this->getConfig()->get('schoolbox_help_access_token'),
                ]
            )
        ];
    }

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}



