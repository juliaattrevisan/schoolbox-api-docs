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
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Config;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{

    public function initRoutes(Router $router, $basePath)
    {
        $routes = $router->getRoutes();
        $routes->addGet('resourceBrowser.browse', $basePath.'{/folderId}')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet('resourceBrowser.listResources', $basePath.'/{folderId}/children')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet('resourceBrowser.showFolderInfo', $basePath.'/{folderId}/info')->addTokens([ 'folderId' => '\d+' ]);
        $routes->addGet('resourceBrowser.showFileInfo', $basePath.'/file/{fileId}/info')->addTokens([ 'fileId' => '\d+' ]);
    }



    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^resourceBrowser\.(?:.+)$/', new Rule\IsLoggedIn());
    }


    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'resourceBrowser');
        $templateLoader->addLoader($loader);
    }


    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:resourceBrowser',
            $injector->lazyNew(Controller::class)
        );

        $setters = [
            'setFolderMapper'     => Resource\Folder\Mapper::class,
            'setFileMapper'       => Resource\FileMapper::class,
            'setUserMapper'       => UserMapper::class,
            'setTaskMapper'       => TaskMapper::class,
            'setUserAccessMapper' => UserAccessMapper::class,
            'setRoleAccessMapper' => RoleAccessMapper::class,
            'setConfig'           => Config\Loader::class
        ];

        foreach ($setters as $method => $class) {
            $injector->setters[Controller::class][$method] = $injector->lazyNew($class);
        }

    }

} 