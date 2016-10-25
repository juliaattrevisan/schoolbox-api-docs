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
use Twig_Loader_Chain;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress_Date_Format;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'statistics';

    /**
     * Module constructor.
     * @param ContainerInterface $injector
     * @param string $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        parent::__construct($injector, $basename);
    }

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
        // Add permissions to each route

        $permissionIndex = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF)
        );

        $this->addRoutePermission($authorizer, 'getIndex', $permissionIndex);
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
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /** Here be getters and setters */

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var ErrorMapper */
    protected $errorMapper;

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $mapper
     * @return $this
     */
    public function setFolderMapper(FolderMapper $mapper)
    {
        $this->folderMapper = $mapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $mapper
     * @return $this
     */
    public function setErrorMapper(ErrorMapper $mapper)
    {
        $this->errorMapper = $mapper;
        return $this;
    }
}