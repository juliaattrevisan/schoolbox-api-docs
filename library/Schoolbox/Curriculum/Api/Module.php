<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;

/**
 * Module for the curriculum API.
 */
class Module extends BaseModule
{
    protected $basename = 'curriculumApi';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // For now: superusers and curriculum service only
        $hasApiAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(
                    UserAccess::SERVICE_CURRICULUM,
                    UserAccess::ACCESS_WRITE
                )
            )
        );
        $preload = function (array $params) {
            if (isset($params['id'])) {
                return [
                    'node' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
                ];
            }
            return [];
        };
        $this->register(self::METHOD_GET, '', 'searchNode', $hasApiAccess);
        $this->register(self::METHOD_GET, '/{id:\\d+}', 'getNode', $hasApiAccess, $preload);
        $this->register(self::METHOD_GET, '/tree', 'getTree', $hasApiAccess);
        $this->register(self::METHOD_POST, '{/id:\\d+}', 'postNode', $hasApiAccess, $preload);
        $this->register(self::METHOD_PUT, '/{id:\\d+}', 'putNode', $hasApiAccess, $preload);
        $this->register(self::METHOD_PATCH, '/{id:\\d+}', 'patchNode', $hasApiAccess, $preload);
        $this->register(self::METHOD_DELETE, '/{id:\\d+}', 'deleteNode', $hasApiAccess, $preload);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(NodeMapper::class),
        );
    }
}
