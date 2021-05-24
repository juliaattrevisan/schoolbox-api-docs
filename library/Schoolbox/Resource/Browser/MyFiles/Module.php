<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\MyFiles;

use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Resource\Browser\FullAccessController;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\My;
use Schoolbox\Resource\Browser\AbstractModule;

class Module extends AbstractModule
{
    protected $basename = 'myFilesBrowser';

    /**
     * @param Container $ci
     * @return array
     */
    protected function getSetterList(Container $ci)
    {
        return array_merge(parent::getSetterList($ci), [
            'setTreeBuilder'  => $ci->lazyNew(TreeBuilder::class),
            'setFolderMapper' => $this->getMapperFactory()->getLazy(My\Folder\Mapper::class),
            'setFileMapper'   => $this->getMapperFactory()->getLazy(My\File\Mapper::class),
            'setNodeType'     => NodeFactory::MYFILE,
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        $setters = $this->getSetterList($injector);

        $injector->setters[TreeBuilder::class]['setFileLocator'] = $setters['setFileMapper'];
        $injector->params[TreeBuilder::class]['locator']         = $setters['setFolderMapper'];
        $injector->params[TreeBuilder::class]['user']            = $injector->lazy(
            function () use ($injector) {
                return $injector->get('session')->getAuthUser();
            }
        );

        $injector->setters[$this->getControllerClass()]['setTab'] = 'myfiles';
    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return FullAccessController::class;
    }
}
