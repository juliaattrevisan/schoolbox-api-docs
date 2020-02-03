<?php
/**
 * Module definition for the tile component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tile;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Component_ConfigMapper;
use Component_Instance;
use Component_InstanceMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Tile\Controller as TileController;
use Schoolbox\Tile\Layout\Mapper as TileLayoutMapper;
use Schoolbox\Tile\Mapper as TileMapper;

class Module extends BaseModule
{
    protected $basename = 'tile';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'layout', $basePath, '/layout/{layoutId}');
        $this->addPostRoute($router, 'layoutForm', $basePath, '/layout/{layoutId}');

        // Attach standard REST routes...
        $this->attachResourceRoutes($router, $basePath);

        // but have separate add tile paths...
        $this->addGetRoute($router, 'add', $basePath, '/add/{layoutId}');
        $this->addPostRoute($router, 'create', $basePath, '/add/{layoutId}');

        // also have separate paths for adding a tile to a component (will create a new layout)
        $this->addGetRoute($router, 'addToComponent', $basePath, '/add/component/{componentId}');
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
                try {
                    $item   = $this->getTileMapper()->findById($params['id']);
                    $layout = $this->getTileLayoutMapper()->findById($item->getTileLayout()->getId());
                } catch (MapperException\Deleted $e) {
                    throw $e;
                } catch (MapperException $e) {
                    throw new NotFound();
                }
                return [
                    'item'   => $item,
                    'layout' => $layout,
                ];
            }
        );

        // Routes that take a layout ID: look up layout from it
        $this->addRoutePreload(
            $matcher,
            '(?:add|create|layout|layoutForm)',
            function ($params) {
                try {
                    $layout = $this->getTileLayoutMapper()->findById($params['layoutId']);
                } catch (MapperException\Deleted $e) {
                    throw $e;
                } catch (MapperException $e) {
                    throw new NotFound();
                }
                return [
                    'layout' => $layout,
                ];
            }
        );

        // Component-related routes only: look up homepage from component
        $this->addRoutePreload(
            $matcher,
            '(?:addToComponent)',
            function ($params) {
                // Load component first
                $component = null;
                if ($params['componentId']) {
                    $component = $this->getComponentInstanceMapper()->findByInstanceId($params['componentId']);
                }

                // Load homepage from component
                $extraParams = [];
                if ($component instanceof Component_Instance) {
                    $extraParams['component'] = $component;
                }
                return $extraParams;
            }
        );
    }

    /**
     * Layouts are editable by:
     * * Superusers
     * * Any user who has write permission to that homepage
     * * Any user who is the teacher of a class connected to that page
     * @param Authorizer $authorizer
     * @throws \InvalidArgumentException
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
                throw new \Exception('Homepage permission does not apply to dashboard tiles');
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
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                TileController::class,
                [
                    'mapper'                => $this->getMapperFactory()->getLazy(TileMapper::class),
                    'layoutMapper'          => $this->getMapperFactory()->getLazy(TileLayoutMapper::class),
                    'componentConfigMapper' => $this->getMapperFactory()->getLazy(Component_ConfigMapper::class),
                    'folderMapper'          => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'errorMapper'           => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                ]
            )
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

    /**
     * Required for fetching the tile layout mapper.
     *
     * @return Layout\Mapper
     */
    public function getTileLayoutMapper()
    {
        return $this->getMapperFactory()->get(Layout\Mapper::class);
    }
}
