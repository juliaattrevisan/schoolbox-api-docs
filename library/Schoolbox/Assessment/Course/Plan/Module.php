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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

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
        $getCourse = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasHomepageAccess(
                    UserAccess::ACCESS_READ,
                    static function (array $params): int {
                        if (isset($params['course']) && $params['course'] instanceof CourseSpecificationModel) {
                            return $params['course']->getFolder()->getId();
                        }
                        throw new \Schoolbox\Application\Exception\NotFound('No context preloaded');
                    },
                    $this->getMapperFactory()->get(Access::class),
                ),
            )
        );

        $editCourse = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasHomepageAccess(
                    UserAccess::ACCESS_WRITE,
                    static function (array $params): int {
                        if (isset($params['course']) && $params['course'] instanceof CourseSpecificationModel) {
                            return $params['course']->getFolder()->getId();
                        }
                        throw new \Schoolbox\Application\Exception\NotFound('No context preloaded');
                    },
                    $this->getMapperFactory()->get(Access::class),
                ),
            )
        );

        $superOnly = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser()
            )
        );

        $preloadCourse = function ($params) {
            return $this->preloadCourse($params);
        };

        $this->register('GET', '/{id:\d+}', 'getPlan', $getCourse, $preloadCourse);
        $this->register('GET', '/audit', 'getAudit', $superOnly);
        $this->register('POST', '/{id:\d+}/newUnit', 'newUnit', $editCourse, $preloadCourse);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getAssessmentMapperProxy(),
            $this->getRubricIndicatorMapper(),
            $this->getNodeMapper(),
            $this->getComponentInstanceMapper(),
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(CourseConfigMapper::class),
            $this->getMapperFactory()->get(UnitSpecificationMapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Preloads the course so we can extract the units and course info
     * @param array $params
     * @return array
     * @throws NotFound
     */
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

    /**
     * @return CourseSpecificationMapper
     */
    private function getCourseSpecificationMapper(): CourseSpecificationMapper
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }

    /**
     * @return AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy(): AssessmentMapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return RubricIndicatorMapper
     */
    private function getRubricIndicatorMapper(): RubricIndicatorMapper
    {
        return $this->getMapperFactory()->get(RubricIndicatorMapper::class);
    }

    /**
     * @return NodeMapper
     */
    private function getNodeMapper(): NodeMapper
    {
        return $this->getMapperFactory()->get(NodeMapper::class);
    }

    /**
     * @return \Component_InstanceMapper
     */
    private function getComponentInstanceMapper(): \Component_InstanceMapper
    {
        return $this->getMapperFactory()->get(\Component_InstanceMapper::class);
    }
}
