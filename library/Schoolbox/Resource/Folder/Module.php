<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Curriculum;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;
use Schoolbox\YearLevel;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'folder';

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $loggedInAccess = new Rule\IsLoggedIn();

        $parentWriteAccess = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            static function (array $params) {
                return $params['parentId'];
            },
            Access::getInstance()
        );

        $preloadParent = function (array $params) {
            if (($params['parent'] ?? null) instanceof Model) {
                return ['parent' => $params['parent']];
            }
            try {
                return ['parent' => $this->getMapper()->findById($params['parentId'])];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $folderWriteAccess = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            static function (array $params) {
                return $params['id'];
            },
            Access::getInstance()
        );
        $preloadFolder     = function (array $params) {
            if (($params['folder'] ?? null) instanceof Model) {
                return ['folder' => $params['folder']];
            }
            try {
                return ['folder' => $this->getMapper()->findById($params['id'])];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $permWriteBoth = new Rule\All($folderWriteAccess, $parentWriteAccess);
        $preloadBoth   = function (array $params) {
            try {
                return [
                    'folder'    => ($params['folder'] ?? null) instanceof Model
                        ? $params['folder']
                        : $this->getMapper()->findById($params['id']),
                    'newParent' => ($params['newParent'] ?? null) instanceof Model
                        ? $params['newParent']
                        : $this->getMapper()->findById($params['parentId']),
                ];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        // Must have WRITE+ access and folder must not be root folder
        $writeAccessAndNotRoot = new Rule\All(
            $folderWriteAccess,
            new Rule\Callback(
                static function (array $params = []): void {
                    if (!array_key_exists('folder', $params)) {
                        throw new InvalidArgumentException("Expected array key 'folder' does not exist.");
                    }

                    /** @var Model $folder */
                    $folder = $params['folder'];
                    if ($folder->getId() === Model::ROOT_ID) {
                        throw new InvalidPath("Folder is root");
                    }
                }
            )
        );

        // If user is not superuser, only folders without sub-folders can be deleted.
        $canDelete = new Rule\All(
            $writeAccessAndNotRoot,
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\Callback(
                    function (array $params = []): void {
                        if (!array_key_exists('folder', $params)) {
                            throw new InvalidArgumentException("Expected array key 'folder' does not exist.");
                        }

                        /** @var Model $folder */
                        $folder = $params['folder'];
                        if ($this->getMapper()->countByColumn('parent', $folder->getId()) > 0) {
                            throw new InvalidPath("The folder is not empty");
                        }
                    }
                )
            )
        );

        $this->register('GET', '/mygroups', 'getGroups', $loggedInAccess);
        $this->register('GET', '/folder/{parentId:\d+}/create', 'getFolderCreate', $parentWriteAccess, $preloadParent);
        $this->register('POST', '/folder/{parentId:\d+}/create', 'postCreateFolder', $parentWriteAccess, $preloadParent);
        $this->register('GET', '/folder/{id:\d+}/modify', 'getFolderModify', $writeAccessAndNotRoot, $preloadFolder);
        $this->register('POST', '/folder/{id:\d+}/update', 'postUpdateFolder', $writeAccessAndNotRoot, $preloadFolder);
        $this->register('PUT', '/folder/{id:\d+}/move/{parentId:\d+}', 'putMoveFolder', $permWriteBoth, $preloadBoth);
        $this->register('DELETE', '/folder/{id:\d+}', 'deleteFolder', $canDelete, $preloadFolder);
        $this->register('POST', '/folder/{id:\d+}/reset', 'postFolderReset', $writeAccessAndNotRoot, $preloadFolder);
        $this->register('POST', '/folder/{id:\d+}/sequence', 'postFolderSequence', $writeAccessAndNotRoot, $preloadFolder);
    }

    /**
     * @return Mapper
     * @throws InvalidArgumentException
     */
    private function getMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(HomepageConfigMapper::class),
            $this->getMapperFactory()->get(HomepageTemplateMapper::class),
            $this->getMapperFactory()->get(RoleAccessMapper::class),
            $this->getMapperFactory()->get(UserAccessMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(Error\Mapper::class),
            $this->getMapperFactory()->get(Access::class),
            $this->getMapperFactory()->get(Curriculum\Node\Mapper::class),
            $this->getMapperFactory()->get(GroupMapper::class),
            $this->getMapperFactory()->get(File\Mapper::class),
            $this->getMapperFactory()->get(YearLevel\Mapper::class),
        );
    }
}
