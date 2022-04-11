<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{
    protected $basename = 'resourceBrowser';

    /**
     * Add a link route as well
     *
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);
        $this->addGetRoute($router, 'showLinkInfo', $basePath, '/link/{linkId}/info')->addTokens([ 'linkId' => '\d+' ]);
        $this->addGetRoute($router, 'showFiles', $basePath, '/folder/{componentID}/files/{sourceFolderID}')->addTokens([ 'componentID' => '\d+', 'sourceFolderID' => '\d+' ]);
        $router->getRoutes()->addPatch($this->basename . '.' . 'patchFileInfo', $basePath . '/file/{fileId}/ajax')->addTokens([ 'fileId' => '\d+' ]);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $treeBuilder = new TreeBuilder(
            $this->getMapperFactory()->get(Resource\Folder\Mapper::class),
            $this->injector->get('user'),
            $this->injector->get('l10n')->translate('resource', true, true),
        );
        $treeBuilder->setFileLocator($this->getMapperFactory()->get(Resource\File\Mapper::class));
        return (
            new Controller(
                $this->getMapperFactory()->get(Resource\File\Mapper::class),
                $this->getMapperFactory()->get(\Component_InstanceMapper::class),
            ))
            ->setLinkMapper($this->getMapperFactory()->get(Resource\LinkMapper::class))
            ->setFileMapper($this->getMapperFactory()->get(Resource\File\Mapper::class))
            ->setConfig($this->getConfig())
            ->setUserMapper($this->getMapperFactory()->get(UserMapper::class))
            ->setNodeFactory(new Resource\Browser\NodeFactory())
            ->setTreeBuilder($treeBuilder)
            ->setFolderMapper($this->getMapperFactory()->get(Resource\Folder\Mapper::class))
            ->setNamespace($this->basename)
            ->setNodeType(Resource\Browser\NodeFactory::SBFS)
            ->setTab('resources');
    }
}
