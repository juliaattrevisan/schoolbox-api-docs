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
            'setFolderMapper' => $ci->lazyNew(My\Folder\Mapper::class),
            'setFileMapper'   => $ci->lazyNew(My\File\Mapper::class),
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
    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return FullAccessController::class;
    }

}