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
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Curriculum;
use Schoolbox\Error;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
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
                '/folder/update'                        => 'postUpdateFolder',
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
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\All(
                new Rule\IsLoggedIn()
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
     * @throws \InvalidArgumentException
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
                'fileMapper'             => $mapperFactory->getLazy(File\Mapper::class),
                'yearLevelMapper'        => $mapperFactory->getLazy(YearLevel\Mapper::class),
            ])
        );
    }
}
