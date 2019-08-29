<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Curriculum;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;
use Schoolbox\YearLevel;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'folder';

    /**
     * Initializes any routes for this module, with a given base path.
     *
     * The base path for each route needs to match up to the name of the
     * controller in $this->initControllers(), so if its base path is
     * "user", there must be a controller in the DI container called
     * "controller:user"
     *
     * @param Router $router
     * @param string $basePath The base route for this module
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/folder/modify/{id}'                   => 'getFolderModify',
                '/folder/create/{parentId}'             => 'getFolderCreate',
                '/mygroups'                             => 'getGroups',
            ],
            'POST' => [
                '/folder/move'                          => 'postMoveFolder',
                '/folder/delete'                        => 'postDeleteFolder',
                '/folder/reset'                         => 'postFolderReset',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // POST Create
        $permCreate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, static function (array $params): int {
                return (int)$params['parentId'];
            }, Access::getInstance())
        );
        $preloadCreate = function (array $params) {
            if (($params['parent'] ?? null) instanceof Model) {
                return ['parent' => $params['parent']];
            }
            return ['parent' => $this->getMapper()->findById($params['parentId'])];
        };
        $this->register('POST', '/folder/{parentId:\d+}/create', 'postCreateFolder', $permCreate, $preloadCreate);

        // POST Update
        $permUpdate    = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, static function (array $params): int {
                return (int)$params['folderId'];
            }, Access::getInstance())
        );
        $preloadUpdate = function (array $params) {
            if (($params['folder'] ?? null) instanceof Model) {
                return ['folder' => $params['folder']];
            }
            return ['folder' => $this->getMapper()->findById($params['folderId'])];
        };
        $this->register('POST', '/folder/{folderId:\d+}/update', 'postUpdateFolder', $permUpdate, $preloadUpdate);
    }

    /**
     * @inheritDoc
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $this
            ->addRoutePreload(
                $matcher,
                '(?:getFolderCreate)',
                function (array $params) {
                    if (($params['parent'] ?? null) instanceof Model) {
                        $parent = $params['parent'];
                    } else {
                        try {
                            $parent = $this->getMapper()->findById($params['parentId']);
                        } catch (MapperException $e) {
                            throw new NotFound("No folder found for {$params['parentId']}", 0, $e);
                        }
                    }
                    return ['parent' => $parent,];
                }
            )
            ->addRoutePreload(
                $matcher,
                '(?:getFolderModify)',
                function (array $params) {
                    if (($params['folder'] ?? null) instanceof Model) {
                        $folder = $params['folder'];
                    } else {
                        try {
                            $folder = $this->getMapper()->findById($params['id']);
                            $folder->setParent($this->getMapper()->findById($folder->getParent()->getId()));
                        } catch (MapperException $e) {
                            throw new NotFound("No folder found for {$params['id']}", 0, $e);
                        }
                    }
                    return ['folder' => $folder,];
                }
            );
    }

    /**
     * @return Mapper
     * @throws InvalidArgumentException
     */
    private function getMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, 'getFolderCreate', new Rule\HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                static function (array $params): int {
                    return (int)$params['parentId'];
                },
                Access::getInstance()
            ))
            ->addRoutePermission($authorizer, 'getFolderModify', new Rule\HasHomepageAccess(
                UserAccess::ACCESS_WRITE,
                static function (array $params): int {
                    return (int)$params['id'];
                },
                Access::getInstance()
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $mapperFactory = $this->getMapperFactory();
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'folderMapper'           => $mapperFactory->getLazy(Mapper::class),
                'homepageConfigMapper'   => $mapperFactory->getLazy(HomepageConfigMapper::class),
                'homepageTemplateMapper' => $mapperFactory->getLazy(HomepageTemplateMapper::class),
                'roleAccessMapper'       => $mapperFactory->getLazy(RoleAccessMapper::class),
                'userAccessMapper'       => $mapperFactory->getLazy(UserAccessMapper::class),
                'roleMapper'             => $mapperFactory->getLazy(RoleMapper::class),
                'errorMapper'            => $mapperFactory->getLazy(Error\Mapper::class),
                'access'                 => $mapperFactory->getLazy(Access::class),
                'classCodeMapper'        => $mapperFactory->getLazy(Code\Mapper::class),
                'curriculumNodeMapper'   => $mapperFactory->getLazy(Curriculum\Node\Mapper::class),
                'groupMapper'            => $mapperFactory->getLazy(GroupMapper::class),
                'fileMapper'             => $mapperFactory->getLazy(File\Mapper::class),
                'yearLevelMapper'        => $mapperFactory->getLazy(YearLevel\Mapper::class),
            ])
        );
    }
}
