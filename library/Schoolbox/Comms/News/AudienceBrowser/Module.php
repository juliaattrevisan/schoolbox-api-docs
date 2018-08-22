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
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\Resource;
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

        $this->register('GET', '/campuses', 'getCampuses', $getPerms);
        $this->register('GET', '/roles', 'getRoles', $getPerms);
        $this->register('GET', '/houses', 'getHouses', $getPerms);
        $this->register('GET', '/classes', 'getClasses', $getPerms);
        $this->register('GET', '/folders', 'getFolders', $getPerms);
        $this->register('GET', '/yearlevels', 'getYearLevels', $getPerms);
        $this->register('GET', '/pastoralgroups', 'getPastoralGroups', $getPerms);
        $this->register('GET', '/search', 'getSearch', $getPerms);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
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
            'groupMapper' => $this->getMapperFactory()->getLazy(Resource\GroupMapper::class),
            'pastoralGroupMapper' => $this->getMapperFactory()->getLazy(Pastoral\Group\Mapper::class),
            'audienceMapper' => $this->getMapperFactory()->getLazy(Audience\Mapper::class),
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
