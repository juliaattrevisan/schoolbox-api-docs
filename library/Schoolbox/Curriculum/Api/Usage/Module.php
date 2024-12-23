<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api\Usage;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;

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
            $this->injector->get('databaseInternal'),
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(Folder\Mapper::class),
            $this->getMapperFactory()->get(CourseSpec\Mapper::class),
            $this->getMapperFactory()->get(UnitSpec\Mapper::class),
            $this->injector->get('assessmentDataSource'),
            new Counter($this->injector->get('databaseInternal'), $this->getMapperFactory()->get(ConfigMapper::class)),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Badge\Mapper::class),
        );
    }
}
