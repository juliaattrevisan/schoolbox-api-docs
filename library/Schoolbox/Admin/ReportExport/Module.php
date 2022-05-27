<?php

/**
 * Report export admin page
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\CampusMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

class Module extends BaseModule
{
    protected $basename = 'adminReportExport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, '');
        $this->addGetRoute($router, 'getTerms', $basePath, '/getTerms');
        $this->addGetRoute($router, 'getYearLevels', $basePath, '/getYearLevels');
        $this->addGetRoute($router, 'getCampuses', $basePath, '/getCampuses');
        $this->addGetRoute($router, 'getWorkTypes', $basePath, '/getWorkTypes');
        $this->addPostRoute($router, 'postReport', $basePath, '/postReport');
        $this->addGetRoute($router, 'getStudentReport', $basePath, '/student/{studentId}');
        $this->addPostRoute($router, 'postConfig', $basePath, '/postConfig');
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
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function ($params) {
            $preloads = [];
            if (isset($params['student']) && $params['student'] instanceof User) {
                $preloads['student'] = $params['student'];
            }
            if (isset($params['studentId'])) {
                $preloads['student'] = $this->getMapperFactory()->get(UserMapper::class)->findById($params['studentId']);
            }
            return $preloads;
        });
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(YearLevelMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(WorkTypeMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(Access::class),
            $this->getMapperFactory()->get(UserBulkMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\RoleMapper::class),
            $this->injector->get('user.tokenBuilder'),
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
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
