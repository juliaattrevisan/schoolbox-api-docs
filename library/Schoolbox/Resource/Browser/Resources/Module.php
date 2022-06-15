<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{
    protected $basename = 'resourceBrowser';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $preloadFile           = function ($params) {
            return $this->preloadFile($params);
        };
        $getHomepageIdCallback = function ($params) {
            if (isset($params['file']) && $params['file'] instanceof Resource\File\Model) {
                return $params['file']->getFolder()->getId();
            }
            if (isset($params['link']) && $params['link'] instanceof Resource\Link) {
                return $params['link']->getFolder()->getId();
            }
            throw new Unauthorized();
        };
        $preloadFolder         = function ($params) {
            return $this->preloadFolder($params);
        };
        $preloadLink           = function ($params) {
            return $this->preloadLink($params);
        };

        $hasEditAccess =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, $getHomepageIdCallback, Access::getInstance())
                )
            );

        $hasFolderReadAccess =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_READ,
                        function ($params): int {
                            if (isset($params['sourceFolder']) && $params['sourceFolder'] instanceof Resource\Folder\Model) {
                                return $params['sourceFolder']->getId();
                            }
                            if (isset($params['file']) && $params['file'] instanceof Resource\File\Model) {
                                return $params['file']->getFolder()->getId();
                            }
                            if (isset($params['link']) && $params['link'] instanceof Resource\Link) {
                                return $params['link']->getFolder()->getId();
                            }
                            throw new Unauthorized();
                        },
                        Access::getInstance()
                    )
                )
            );

        $hasReadAccess =
            new Rule\Any (
                $hasEditAccess,
                new Rule\All(
                    $hasFolderReadAccess,
                    new Rule\Callback(function (array $params): bool {
                        if (
                            isset($params['file'])
                            && $params['file'] instanceof Resource\File\Model
                            && !$params['file']->isHidden()
                        ) {
                            return true;
                        }
                        if (
                            isset($params['link'])
                            && $params['link'] instanceof Resource\Link
                            && !$params['link']->isHidden()
                        ) {
                            return true;
                        }
                        throw new Unauthorized();
                    })
                )
            );

        $this->register('GET', '/folder/{componentID:\d+}/files/{sourceFolderID:\d+}', 'showFiles', $hasFolderReadAccess, $preloadFolder);
        $this->register('GET', '/folder/files/{sourceFolderID:\d+}', 'showFolderFiles', $hasFolderReadAccess, $preloadFolder);
        $this->register('PATCH', '/file/{fileId:\d+}/ajax', 'patchFileInfo', $hasEditAccess, $preloadFile);
        $this->register('GET', '/file/{fileId:\d+}/info', 'showFileInfo', $hasReadAccess, $preloadFile);
        $this->register('GET', '/link/{linkId:\d+}/info', 'showLinkInfo', $hasReadAccess, $preloadLink);
    }

    /**
     * Add a link route as well
     *
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        parent::initRoutes($router, $basePath);
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
        $treeBuilder->setFileLocator(
            new Resource\File\CompositeLocator(
                $this->getMapperFactory()->get(Resource\File\Mapper::class),
                $this->getMapperFactory()->get(Resource\LinkMapper::class)
            )
        );
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

    /**
     * Preload a file object
     *
     * @param array $params
     * @return array
     * @throws Resource\Exception
     */
    private function preloadFile(array $params): array
    {
        try {
            $file = $this->getMapperFactory()->get(FileMapper::class)->findById($params['fileId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }
        if (!$file instanceof Resource\File\Model) {
            throw new Resource\Exception("Requested file is not a file");
        }

        return [
            'file' => $file,
        ];
    }

    /**
     * Preload folder
     *
     * @param array $params
     * @return array
     */
    private function preloadFolder(array $params): array
    {
        if (($params['sourceFolder'] ?? null) instanceof Resource\Folder\Model) {
            return $params;
        }
        try {
            $folder = $this->getMapperFactory()->get(Resource\Folder\Mapper::class)->findById($params['sourceFolderID']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'sourceFolder' => $folder,
        ];
    }

    /**
     * Preload a link object
     *
     * @param array $params
     * @return array
     * @throws Resource\Exception
     */
    private function preloadLink(array $params): array
    {
        try {
            $link = $this->getMapperFactory()->get(Resource\LinkMapper::class)->findById($params['linkId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }
        if (!$link instanceof Resource\Link) {
            throw new Resource\Exception("Requested file is not a link");
        }

        return [
            'link' => $link,
        ];
    }
}
