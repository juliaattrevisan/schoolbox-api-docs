<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\MyFiles;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Alaress\Authorizer\Rule;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\FullAccessController;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\My;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{
    protected $basename = 'myFilesBrowser';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $preloadFile = function ($params) {
            return $this->preloadFile($params);
        };

        $preloadFolder = function ($params) {
            return $this->preloadFolder($params);
        };

        $hasFileAccess =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\Callback(function (array $params, User $user): bool {
                        if (
                            isset($params['file'])
                            && $params['file'] instanceof My\File\Model
                            && $params['file']->getOwner()->getId() === $user->getId()
                        ) {
                            return true;
                        }
                        throw new Unauthorized();
                    })
                )
            );

        $hasFolderAccess =
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\Callback(function (array $params, User $user): bool {
                        if (
                            isset($params['folder'])
                            && $params['folder'] instanceof My\Folder\Model
                            && ($params['folder']->getId() === IFolder::ROOT_ID
                                || $params['folder']->getOwner()->getId() === $user->getId())
                        ) {
                            return true;
                        }
                        throw new Unauthorized();
                    })
                )
            );
        $notSupported    = new Rule\Callback(function (array $params, User $user): bool {
            throw new InvalidPath();
        });
        $this->register('GET', '/file/{fileId:\d+}/ajax', 'getFileInfo', $hasFileAccess, $preloadFile);
        $this->register('GET', '{/folderId:\d+}', 'browse', $hasFolderAccess, $preloadFolder);
        $this->register('GET', '/{folderId:\d+}/children', 'listResources', $hasFolderAccess, $preloadFolder);
        $this->register('GET', '/{folderId:\d+}/nodes', 'listNodes', $hasFolderAccess, $preloadFolder);
        $this->register('GET', '/{folderId:\d+}/info', 'showFolderInfo', $hasFolderAccess, $preloadFolder);
        $this->register('GET', '/file/{fileId:\d+}/info', 'showFileInfo', $hasFileAccess, $preloadFile);
        $this->register('GET', '/file/{fileIds:\d+}/info', 'showMultiFileInfo', $notSupported);
        $this->register('POST', '/{folderId:\d+}/files', 'postFiles', $hasFolderAccess, $preloadFolder);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $treeBuilder = new TreeBuilder(
            $this->getMapperFactory()->get(My\Folder\Mapper::class),
            $this->injector->get('user'),
        );
        $treeBuilder->setFileLocator($this->getMapperFactory()->get(My\File\Mapper::class));
        return (new FullAccessController())
            ->setFileMapper($this->getMapperFactory()->get(My\File\Mapper::class))
            ->setConfig($this->getConfig())
            ->setUserMapper($this->getMapperFactory()->get(UserMapper::class))
            ->setUserAccessMapper($this->getMapperFactory()->get(UserAccessMapper::class))
            ->setNodeFactory(new NodeFactory())
            ->setTreeBuilder($treeBuilder)
            ->setFolderMapper($this->getMapperFactory()->get(My\Folder\Mapper::class))
            ->setNamespace($this->basename)
            ->setNodeType(NodeFactory::MYFILE)
            ->setTab('myfiles');
    }

    /**
     * Preload a file object
     *
     * @param array $params
     * @return array
     * @throws MapperException\NotFound
     */
    private function preloadFile(array $params): array
    {
        try {
            $file = $this->getMapperFactory()->get(My\File\Mapper::class)->findById($params['fileId']);
        } catch (MapperException $e) {
            throw new MapperException\NotFound();
        }
        if (!$file instanceof My\File\Model) {
            throw new MapperException\NotFound();
        }

        return [
            'file' => $file,
        ];
    }

    /**
     * Preload a folder object
     *
     * @param array $params
     * @return array
     * @throws MapperException\NotFound
     */
    private function preloadFolder(array $params): array
    {
        try {
            $folder = $this->getMapperFactory()->get(My\Folder\Mapper::class)->findById($params['folderId']);
        } catch (MapperException $e) {
            throw new MapperException\NotFound();
        }
        if (!$folder instanceof My\Folder\Model) {
            throw new MapperException\NotFound();
        }

        return [
            'folder' => $folder,
        ];
    }
}
