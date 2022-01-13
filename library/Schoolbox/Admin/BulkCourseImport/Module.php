<?php

/**
 * Bulk course import admin page
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkCourseImport;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Course\Import\ImportFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'adminBulkCourseImport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, '');
        $this->addPostRoute($router, 'postImportResults', $basePath, '');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'folderMapper'               => $this->getMapperFactory()->getLazy(FolderMapper::class),
            'userMapper'                 => $this->getMapperFactory()->getLazy(UserMapper::class),
            'courseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
            'unitSpecificationMapper'    => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
            'importFactory'              => $injector->lazyNew(
                ImportFactory::class,
                [
                    'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                    'replicator'            => $injector->lazyNew(
                        Replicator::class,
                        [
                            'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                            'quizMapper'            => $this->getMapperFactory()->getLazy(QuizMapper::class),
                            'rubricMapper'          => $this->getMapperFactory()->getLazy(RubricDataSource::class),
                        ]
                    ),
                ]
            ),
            'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
            'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
        ];
    }
}
