<?php
/**
 * Module definition for statistics actions
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Statistics;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress_Date_Format;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Log\Mapper AS FolderLogMapper;
use Schoolbox\Resource\Folder\Visits\Mapper AS VisitsMapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'statistics';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/homepage/{folderId}/topVisitors{/startDate,endDate,roleFilter}' => 'getTopVisitors',
                '/homepage/{folderId}/topFiles{/startDate,endDate,roleFilter}' => 'getTopFiles',
                '/homepage/{folderId}/recentVisitors{/startDate,endDate,roleFilter}' => 'getRecentVisitors',
                '/homepage/{folderId}/yetToVisit{/startDate,endDate,roleFilter}' => 'getYetToVisit',
                '/homepage/{folderId}/topLinks{/startDate,endDate,roleFilter}' => 'getTopLinks',
                '/homepage/{folderId}/filter' => 'getFilter',
                '/homepage/{folderId}{/startDate,endDate,roleFilter}' => 'getIndex',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $folderMapper = $this->getFolderMapper();
        $errorMapper = $this->getErrorMapper();

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($folderMapper, $errorMapper) {
                /**
                 * Convert folderId into Folder object
                 */
                if (array_key_exists('folderId', $params)) {
                    try {
                        $params['folder'] = $folderMapper->findById($params['folderId']);
                    }
                    catch (MapperException $e) {
                        throw $errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                    }
                }

                /*
                 * Convert the Y-m date formats in the URL to date objects
                 */
                if (array_key_exists('startDate', $params) && $params['startDate'] !== '') {
                    $startDate = new Alaress_Date_Format();
                    $startDate->setFromFormat($params['startDate'], 'Y-m');
                    $params['startDate'] = $startDate->startOfMonth();
                }
                if (array_key_exists('endDate', $params)  && $params['endDate'] !== '') {
                    $endDate = new Alaress_Date_Format();
                    $endDate->setFromFormat($params['endDate'], 'Y-m');
                    $params['endDate'] = $endDate->endOfMonth();
                }

                return $params;
            }
        );
    }



    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        /* Must be superuser or have write (or higher) access to the homepage
         */
        $isSuperUser = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $hasHomepageWrite = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                function ($params) {
                    return $params['folderId'];
                },
                Access::getInstance()
            )
        );

        $permissionIndex = new Rule\Any(
            $isSuperUser,
            $hasHomepageWrite
        );

        $this->addRoutePermission($authorizer, '.*', $permissionIndex);
    }

    /**
     * {@inheritdoc}
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        // dependencies for DataSource
        $injector->params[DataSource::class] = [
            'userMapper'       => $this->getMapperFactory()->getLazy(UserMapper::class),
            'folderLogMapper'  => $this->getMapperFactory()->getLazy(FolderLogMapper::class),
            'visitMapper'      => $this->getMapperFactory()->getLazy(VisitsMapper::class),
            'logMapper'        => $this->getMapperFactory()->getLazy(LogMapper::class),
            'userAccessMapper' => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
        ];

        // dependencies for Controller
        $injector->params[Controller::class] = [
            'dataSource' => $injector->lazyNew(DataSource::class)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
