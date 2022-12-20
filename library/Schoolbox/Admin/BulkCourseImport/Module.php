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
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_InstanceMapper;
use Schoolbox\Assessment\Course\Import\ImportFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->injector->get('assessmentDataSource');
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(CourseImplementationMapper::class),
            $this->getMapperFactory()->get(UnitSpecificationMapper::class),
            new ImportFactory(
                $assessmentDataSource,
                new Replicator(
                    $assessmentDataSource,
                    $this->getMapperFactory()->get(QuizMapper::class),
                    $this->getMapperFactory()->get(RubricDataSource::class),
                ),
                $this->getMapperFactory()->get(CourseImplementationMapper::class),
                $this->getMapperFactory()->get(UnitImplementationMapper::class),
                $this->getMapperFactory()->get(Component_InstanceMapper::class),
                $this->getMapperFactory()->get(Component_ConfigDefinitionMapper::class),
                $this->getMapperFactory()->get(Component_ConfigMapper::class),
            ),
            $assessmentDataSource,
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('filesystem.storageProxy'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
