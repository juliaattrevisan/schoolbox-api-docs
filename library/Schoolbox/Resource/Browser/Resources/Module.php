<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Resources;


use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{

    /**
     * @param ContainerInterface $injector
     */
    public function __construct(ContainerInterface $injector)
    {
        parent::__construct($injector);
        $this->basename = 'resourceBrowser';
    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return Controller::class;
    }


    /**
     * @return array
     */
    protected function getSetterList()
    {
        // add/override the parent list
        return array_merge(parent::getSetterList(), [
            'setTreeBuilder'      => Resource\Browser\Resources\TreeBuilder::class,
            'setFolderMapper'     => Resource\Folder\Mapper::class,
            'setUserMapper'       => UserMapper::class,
            'setUserAccessMapper' => UserAccessMapper::class,
            'setRoleAccessMapper' => RoleAccessMapper::class
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        $setters = $this->getSetterList();

        $injector->params[TreeBuilder::class]['locator']          = $injector->lazyNew($setters['setFolderMapper']);
        $injector->params[TreeBuilder::class]['taskMapper']       = $injector->lazyNew(TaskMapper::class);
        $injector->params[TreeBuilder::class]['userAccessMapper'] = $injector->lazyNew($setters['setUserAccessMapper']);
        $injector->params[TreeBuilder::class]['roleAccessMapper'] = $injector->lazyNew($setters['setRoleAccessMapper']);

        $injector->params[TreeBuilder::class]['user'] = $injector->lazy(
            function () use ($injector) {
                return $injector->get('session')->getAuthUser();
            }
        );

        foreach ($setters as $method => $class) {
            $injector->setters[$this->getControllerClass()][$method] = $injector->lazyNew($class);
        }

    }

} 