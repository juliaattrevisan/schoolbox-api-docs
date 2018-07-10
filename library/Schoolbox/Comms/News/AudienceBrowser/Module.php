<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Mapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;


class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsAudienceBrowser';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Anyone can retrieve options
         * TODO: But maybe it should be staff only?
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

        $this->register('GET', '/campus', 'getCampuses', $getPerms);
        $this->register('GET', '/roles', 'getRoles', $getPerms);
        $this->register('GET', '/house', 'getHouses', $getPerms);
        $this->register('GET', '/class', 'getClasses', $getPerms);
        $this->register('GET', '/group', 'getGroups', $getPerms);
        $this->register('GET', '/pastoral', 'getPastoralGroups', $getPerms);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
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
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'campusMapper' => $this->getMapperFactory()->getLazy(CampusMapper::class),
            'roleMapper' => $this->getMapperFactory()->getLazy(RoleMapper::class),
            'houseMapper' => $this->getMapperFactory()->getLazy(\HouseMapper::class),
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
