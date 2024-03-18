<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Folder;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * Module for modifying curriculum API usage for folders only.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'curriculumApiUsageFolder';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $path       = '/{id:\\d+}/folder/{folderId:\\d+}';
        $permission = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            fn ($params) => $params['folderId'],
            Access::getInstance(),
        );
        $preload = function ($params) {
            return [
                'node'   => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
        };
        $this->register(self::METHOD_POST, $path, 'postFolderUsage', $permission, $preload);
        $this->register(self::METHOD_DELETE, $path, 'deleteFolderUsage', $permission, $preload);
    }

    /**
     * @return Controller
     */
    public function buildController(): Controller
    {
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
        );
    }
}
