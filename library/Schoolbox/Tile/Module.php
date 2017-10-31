<?php
/**
 * Module definition for the tile component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Component_Instance;
use Component_InstanceMapper;
use Component_ConfigMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Tile\Component\TileControllerInterface;
use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Tile\Mapper as TileMapper;
use Schoolbox\Tile\Controller as TileController;


class Module extends BaseModule
{
    protected $basename = 'tile';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'layout', $basePath, '/layout/{componentId}');
        $this->addPostRoute($router, 'layoutForm', $basePath , '/layout/{componentId}');
        // Attach standard REST routes
        $this->attachResourceRoutes($router, $basePath);
        // but have separate add tile paths
        $this->addGetRoute($router, 'add', $basePath, '/add/{componentId}');
        $this->addPostRoute($router, 'create', $basePath, '/add/{componentId}');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Routes that take a tile ID:
        // Look up the tile, and reverse lookup the component from its layout
        $this->addRoutePreload(
            $matcher,
            '(?:edit|update|update:post|delete|delete:post)',
            function ($params) {
                $item = $this->getTileMapper()->findById(
                    $params['id'],
                    array('joins' => array('backgroundImage',),)
                );
                $component = $this->getComponentInstanceMapper()->findComponentByConfigValue(
                    'layoutId',
                    $item->getTileLayout()->getId()
                )->first();
                if (!($component instanceof Component_Instance)) {
                    throw new \RuntimeException('Component does not exist');
                }
                return array(
                    'item'      => $item,
                    'component' => $component,
                );
            }
        );

        // Routes that take a component ID: look up component from it
        $this->addRoutePreload(
            $matcher,
            '(?:add|create|layout|layoutForm)',
            function ($params) {
                $component = $this->getComponentInstanceMapper()->findByInstanceId($params['componentId']);

                // Component must actually exist.
                // This should cause an error that is recorded, but doesn't
                // prevent a login check.
                if (!($component instanceof Component_Instance)) {
                    throw new \RuntimeException('Component does not exist');
                }

                // Also, this component needs to be a tile grid.
                if (!is_a(
                    $component->getDefinition()->getClass(),
                    TileControllerInterface::class,
                    true
                )) {
                    throw new \RuntimeException('Component exists, but is not a tile layout');
                }

                // augment params with component
                return array(
                    'component' => $component,
                );
            }
        );

        // All routes: look up homepage from component
        // (if this component belongs to the homepage!)
        $this->addRoutePreload(
            $matcher,
            "(?:add|create|layout|layoutForm|edit|update|update:post|delete|delete:post)",
            function ($params) {
                // augment params with homepage from category
                if ($params['component']->getModule() == 'homepage') {

                    // Support tile alt source to potentially soft-deleted homepage
                    $folderId   = $params['component']->getIdentifier();
                    $columnName = $this->getFolderMapper()->getIdentifier()['column'];

                    return array(
                        'homepage' => $this->getFolderMapper()->findByColumn($columnName, $folderId, ['deleted' => true])->first(),
                    );
                }
                return array();
            }
        );
    }

    /**
     * Layouts are editable by:
     * * Superusers
     * * Any user who has write permission to that homepage
     * * Any user who is the teacher of a class connected to that page
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // Layouts are editable by anyone with write permission on the homepage.
        // If it's a subject, this could include people involved in subject.
        // For dashboards: the homepage rules don't apply, therefore only
        // superusers may edit dashboard tiles

        // Callback for extracting homepage from route params
        $getHomepageCallback = function ($params) {
            if ($params['component']->getModule() != 'homepage') {
                throw new \Exception('homepage permission does not apply to dashboard tiles');
            }
            return $params['homepage'];
        };

        // Callback for extracting homepage ID from route params
        $getHomepageIdCallback = function ($params) use ($getHomepageCallback) {
            $homepage = $getHomepageCallback($params);
            return $homepage->getId();
        };

        $rule = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, $getHomepageIdCallback, Access::getInstance())
        );
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
        $this->addRoutePermission($authorizer, '.*', $rule);
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->setters[TileController::class]['setMapper']
            = $this->getMapperFactory()->getLazy(TileMapper::class);
        $injector->setters[TileController::class]['setLayoutMapper']
            = $this->getMapperFactory()->getLazy(TileLayoutMapper::class);
        $injector->setters[TileController::class]['setComponentConfigMapper']
            = $this->getMapperFactory()->getLazy(Component_ConfigMapper::class);
        $injector->setters[TileController::class]['setStorageMapper']
            = $this->getMapperFactory()->getLazy(StorageMapper::class);
        $injector->setters[TileController::class]['setFolderMapper']
            = $this->getMapperFactory()->getLazy(FolderMapper::class);
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(TileController::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * Required for fetching the folder that the component belongs to.
     *
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * Required for fetching the component from the database so that access
     * permission to this page may be checked.
     *
     * @return Component_InstanceMapper
     */
    public function getComponentInstanceMapper()
    {
        return $this->getMapperFactory()->get(Component_InstanceMapper::class);
    }

    /**
     * Required for checking the user's access to the homepage that this tile
     * belongs to, based on the role level permissions for that homepage.
     *
     * @return RoleAccessMapper
     */
    public function getRoleAccessMapper()
    {
        return $this->getMapperFactory()->get(RoleAccessMapper::class);
    }

    /**
     * Required for fetching the tile to insert into.
     *
     * @return Mapper
     */
    public function getTileMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }

}
