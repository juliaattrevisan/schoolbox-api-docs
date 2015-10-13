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
     * @param Container $ci
     * @return array
     */
    protected function getSetterList(Container $ci)
    {
        // add/override the parent list
        return array_merge(parent::getSetterList($ci), [
            'setTreeBuilder'      => $ci->lazyNew(Resource\Browser\Resources\TreeBuilder::class),
            'setFolderMapper'     => $ci->lazyNew(Resource\Folder\Mapper::class),
            'setFileMapper'       => $ci->lazyNew(Resource\File\Mapper::class),
            'setUserAccessMapper' => $ci->lazyNew(UserAccessMapper::class),
            'setRoleAccessMapper' => $ci->lazyNew(RoleAccessMapper::class),
            'setNodeType'         => Resource\Browser\NodeFactory::SBFS,
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        $setters = $this->getSetterList($injector);

        $injector->params[TreeBuilder::class]['taskMapper']       = $injector->lazyNew(TaskMapper::class);
        $injector->params[TreeBuilder::class]['locator']          = $setters['setFolderMapper'];
        $injector->params[TreeBuilder::class]['userAccessMapper'] = $setters['setUserAccessMapper'];
        $injector->params[TreeBuilder::class]['roleAccessMapper'] = $setters['setRoleAccessMapper'];
        $injector->setters[TreeBuilder::class]['setFileLocator']  = $setters['setFileMapper'];

        $injector->params[TreeBuilder::class]['user'] = $injector->lazy(
            function () use ($injector) {
                return $injector->get('session')->getAuthUser();
            }
        );

    }

} 