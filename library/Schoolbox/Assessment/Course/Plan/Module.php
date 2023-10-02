<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'coursePlan';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );

        $preloadCourse = function ($params) {
            return $this->preloadCourse($params);
        };

        $this->register('GET', '/{id:\d+}', 'getPlan', $getPerms, $preloadCourse);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(UserMapper::class)
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    private function preloadCourse(array $params): array
    {
        try {
            $course = $this->getCourseSpecificationMapper()->findById($params['id']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'course' => $course,
        ];
    }

    private function getCourseSpecificationMapper()
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }
}
