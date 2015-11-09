<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\WebDav;


use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use PortfolioWebDAV;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\Resource\Browser\FullAccessController;
use Schoolbox\Resource\Browser\NodeFactory;

class Module extends AbstractModule
{
    protected $basename = 'webDavBrowser';

    protected $moduleDependencies = ['resourceBrowser'];

    /**
     * @param Container $ci
     * @return array
     */
    protected function getSetterList(Container $ci)
    {
        $lazyDav = $ci->lazy(function () {
            return PortfolioWebDAV::factory();
        });

        return array_merge(parent::getSetterList($ci), [
            'setTreeBuilder'  => $ci->lazyNew(TreeBuilder::class),
            'setNodeType'     => NodeFactory::WEBDAV,
            'setFolderMapper' => $lazyDav,
            'setFileMapper'   => $lazyDav,
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        // noop
    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return FullAccessController::class;
    }

}