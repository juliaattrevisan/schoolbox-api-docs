<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Folder;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
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
        // Routes for adding/removing a single node from a folder
        $path       = '/{nodeId:\\d+}/folder/{folderId:\\d+}';
        $permission = new Rule\HasHomepageAccess(
            UserAccess::ACCESS_WRITE,
            fn ($params) => $params['folderId'],
            Access::getInstance(),
        );
        $preload    = function ($params) {
            $return = [
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
            if (isset($params['nodeId'])) {
                $return['node'] = $this->getMapperFactory()->get(NodeMapper::class)->findById($params['nodeId']);
            }
            return $return;
        };
        $this->register(self::METHOD_POST, $path, 'postSingleFolderUsage', $permission, $preload);
        $this->register(self::METHOD_DELETE, $path, 'deleteSingleFolderUsage', $permission, $preload);

        // Route for updating folder's nodes all at once
        $this->register(self::METHOD_POST, '/folder/{folderId:\\d+}', 'postFolderUsage', $permission, $preload);
        // Route for getting folder's nodes all at once
        $this->register(self::METHOD_GET, '/folder/{folderId:\\d+}', 'getFolderUsage', $permission, $preload);
    }

    /**
     * @return Controller
     */
    public function buildController(): Controller
    {
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(NodeMapper::class),
        );
    }
}
