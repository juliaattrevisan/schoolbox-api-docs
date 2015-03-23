<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\MyFiles;


use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\MyFileMapper;
use Schoolbox\MyFolderMapper;
use Schoolbox\Resource\Browser\AbstractModule;

class Module extends AbstractModule
{
    /**
     * @param ContainerInterface $injector
     */
    public function __construct(ContainerInterface $injector)
    {
        parent::__construct($injector);
        $this->basename = 'myFilesBrowser';
    }


    /**
     * @return array
     */
    protected function getSetterList()
    {
        return array_merge(parent::getSetterList(), [
            'setTreeBuilder'  => TreeBuilder::class,
            'setFolderMapper' => MyFolderMapper::class,
            'setFileMapper'   => MyFileMapper::class,
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        $setters = $this->getSetterList();

        $injector->params[TreeBuilder::class]['locator'] = $injector->lazyNew($setters['setFolderMapper']);
        $injector->params[TreeBuilder::class]['user']    = $injector->lazy(
            function () use ($injector) {
                return $injector->get('session')->getAuthUser();
            }
        );

        foreach ($setters as $method => $class) {
            $injector->setters[$this->getControllerClass()][$method] = $injector->lazyNew($class);
        }

    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return Controller::class;
    }

} 