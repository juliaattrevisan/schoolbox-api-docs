<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Api\Controller;
use Schoolbox\Curriculum\Node\Mapper;

/**
 * Module for the curriculum usage API.
 */
class Module extends BaseModule
{
    protected $basename = 'curriculumApiUsage';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $hasAccess = new Rule\IsLoggedIn();
        $preload   = function (array $params) {
            if (isset($params['id'])) {
                return [
                    'node' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
                ];
            }
            return [];
        };
        $this->register(
            self::METHOD_GET,
            '/{id:\\d+}',
            'getNodeUsage',
            $hasAccess,
            $preload,
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
        );
    }
}
