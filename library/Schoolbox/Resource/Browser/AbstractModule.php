<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Router\Map;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;

abstract class AbstractModule extends BaseModule
{
    /**
     * @param \Aura\Router\Map $router
     * @param string $basePath
     */
    public function initRoutes(Map $routeMap, $basePath)
    {
        $this->addGetRoute($routeMap, 'picker', $basePath, '/picker/{sourceSlug}');
        $this->addGetRoute($routeMap, 'getFileInfo', $basePath, '/file/{fileId}/ajax')->addTokens(['fileId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'browse', $basePath, '{/folderId}')->addTokens(['folderId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'listResources', $basePath, '/{folderId}/children')->addTokens(['folderId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'listNodes', $basePath, '/{folderId}/nodes')->addTokens(['folderId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'showFolderInfo', $basePath, '/{folderId}/info')->addTokens(['folderId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'showFileInfo', $basePath, '/file/{fileId}/info')->addTokens(['fileId' => '\d+' ]);
        $this->addGetRoute($routeMap, 'showMultiFileInfo', $basePath, '/file/{fileIds}/info')->addTokens(['fileIds' => '(\d+,)+(\d+)' ]);
        $this->addPostRoute($routeMap, 'postFiles', $basePath, '/{folderId}/files')->addTokens(['folderId' => '\d+' ]);
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '(?:.+)', new Rule\IsLoggedIn());
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws \Twig\Error\LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
