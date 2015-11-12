<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Config;
use Schoolbox\Resource;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

abstract class AbstractModule extends BaseModule
{
    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'browse', $basePath, '{/folderId}')->addTokens([ 'folderId' => '\d+' ]);
        $this->addGetRoute($router, 'listResources', $basePath, '/{folderId}/children')->addTokens([ 'folderId' => '\d+' ]);
        $this->addGetRoute($router, 'listNodes', $basePath, '/{folderId}/nodes')->addTokens([ 'folderId' => '\d+' ]);
        $this->addGetRoute($router, 'showFolderInfo', $basePath, '/{folderId}/info')->addTokens([ 'folderId' => '\d+' ]);
        $this->addGetRoute($router, 'showFileInfo', $basePath, '/file/{fileId}/info')->addTokens([ 'fileId' => '\d+' ]);
        $this->addGetRoute($router, 'showMultiFileInfo', $basePath, '/file/{fileIds}/info')->addTokens([ 'fileIds' => '(\d+,)+(\d+)' ]);
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '(?:.+)', new Rule\IsLoggedIn());
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws \Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__.'/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        // set
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew($this->getControllerClass(), [], $this->getSetterList($injector))
        );

        $this->initSetters($injector);
    }


    /**
     * @param Container $ci
     * @return array
     */
    protected function getSetterList(Container $ci)
    {
        return [
            'setFileMapper'       => null,
            'setConfig'           => $ci->lazyNew(Config\Loader::class),
            'setUserMapper'       => $ci->lazyNew(UserMapper::class),
            'setNodeFactory'      => $ci->lazyNew(NodeFactory::class),
            'setTreeBuilder'      => null,
            'setFolderMapper'     => null,
            'setNamespace'        => $this->basename,
            'setNodeType'         => '',
        ];
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected abstract function initSetters(Container $injector);


    /**
     * @return string
     */
    protected abstract function getControllerClass();

} 