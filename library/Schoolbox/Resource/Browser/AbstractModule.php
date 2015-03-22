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
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

abstract class AbstractModule extends BaseModule
{
    /** @var string */
    protected $basename = '';


    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = $router->getRoutes();
        $routes->addGet("$this->basename.browse", $basePath.'{/folderId}')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet("$this->basename.listResources", $basePath.'/{folderId}/children')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet("$this->basename.showFolderInfo", $basePath.'/{folderId}/info')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet("$this->basename.showFileInfo", $basePath.'/file/{fileId}/info')->addTokens([ 'fileId' => '\d+' ]);
        $routes->addGet("$this->basename.showMultiFileInfo", $basePath.'/file/{fileIds}/info')->addTokens([ 'folderId' => '(\d+,)+(\d+)' ]);
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule("/^{$this->basename}\.(?:.+)$/", new Rule\IsLoggedIn());
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws \Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', $this->basename);
        $templateLoader->addLoader($loader);
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        // set
        $injector->set(
            "controller:$this->basename",
            $injector->lazyNew($this->getControllerClass())
        );

        $this->initSetters($injector);
    }


    /**
     * @return array
     */
    protected function getSetterList()
    {
        return [
            'setFileMapper'       => Resource\FileMapper::class,
            'setConfig'           => Config\Loader::class,
            'setTreeBuilder'      => null,
            'setFolderMapper'     => null,
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