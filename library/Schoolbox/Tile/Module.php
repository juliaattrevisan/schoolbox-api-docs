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
use External_UserInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    /**
     * Dependency on tile mapper.
     *
     * Required for fetching the tile to insert into.
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * Dependency on category mapper.
     *
     * Required for fetching the category that the component belongs to.
     *
     * @var Folder\Mapper
     */
    protected $categoryMapper;

    /**
     * Dependency on component instance mapper.
     *
     * Required for fetching the component from the database so that access
     * permission to this page may be checked.
     *
     * @var Component_InstanceMapper
     */
    protected $componentInstanceMapper;

    /**
     * Dependency on role access mapper.
     *
     * Required for checking the user's access to the homepage that this tile
     * belongs to, based on the role level permissions for that homepage.
     *
     * @var RoleAccessMapper
     */
    protected $roleAccessMapper;

    /**
     * Dependency on external user database.
     *
     * Required for checking that the requesting user is a teacher of the class
     * homepage that this component is on.
     *
     * @var External_UserInterface
     */
    protected $externalDb;

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
        // Add the component and homepage to params,
        // as they're used in permission checks
        $mapper          = $this->getMapper();
        $catMapper       = $this->getCategoryMapper();
        $componentMapper = $this->getComponentInstanceMapper();

        // Routes that take a tile ID:
        // Look up the tile, and reverse lookup the component from its layout
        $this->addRoutePreload(
            $matcher,
            '(?:edit|update|update:post|delete|delete:post)',
            function ($params) use ($mapper, $componentMapper) {
                $item = $mapper->findById(
                    $params['id'],
                    array('joins' => array('backgroundImage',),)
                );
                $component = $componentMapper->findComponentByConfigValue(
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
            function ($params) use ($componentMapper) {
                $component = array_pop($componentMapper->findByInstanceId($params['componentId']));

                // Component must actually exist.
                // This should cause an error that is recorded, but doesn't
                // prevent a login check.
                if (!($component instanceof Component_Instance)) {
                    throw new \RuntimeException('Component does not exist');
                }

                // Also, this component needs to be a tile grid.
                if (!is_a(
                    $component->getDefinition()->getClass(),
                    'Schoolbox\\Tile\\Component\\TileControllerInterface',
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
            function ($params) use ($catMapper) {
                // augment params with homepage from category
                if ($params['component']->getModule() == 'homepage') {
                    return array(
                        'homepage' => $catMapper->findById($params['component']->getIdentifier()),
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
            new Rule\HasServicePermission(
                UserAccess::SERVICE_HOMEPAGE,
                UserAccess::ACCESS_WRITE,
                $getHomepageIdCallback
            ),
            new Rule\HasHomepagePermissionThroughRole(
                UserAccess::ACCESS_WRITE,
                $getHomepageIdCallback,
                $this->getRoleAccessMapper()
            ),
            new Rule\IsTeacherOfClass($this->getExternalDb(), $getHomepageCallback)
        );
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
        $this->addRoutePermission($authorizer, '.*', $rule);
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->setters['Schoolbox\\Tile\\Controller']['setMapper']
            = $injector->lazyNew('Schoolbox\\Tile\\Mapper');
        $injector->setters['Schoolbox\\Tile\\Controller']['setLayoutMapper']
            = $injector->lazyNew('Schoolbox\\Tile\\Layout\\Mapper');
        $injector->setters['Schoolbox\\Tile\\Controller']['setComponentConfigMapper']
            = $injector->lazyNew('Component_ConfigMapper');
        $injector->setters['Schoolbox\\Tile\\Controller']['setStorageMapper']
            = $injector->lazyNew(Storage\Mapper::class);
        $injector->setters['Schoolbox\\Tile\\Controller']['setFolderMapper']
            = $this->getCategoryMapper();
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Schoolbox\\Tile\\Controller')
        );
    }

    /**
     * @param  Folder\Mapper $categoryMapper
     * @return $this
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }

    /**
     * @return Folder\Mapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  Component_InstanceMapper $componentInstanceMapper
     * @return $this
     */
    public function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
        return $this;
    }

    /**
     * @return Component_InstanceMapper
     */
    public function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * @return RoleAccessMapper
     */
    public function getRoleAccessMapper()
    {
        return $this->roleAccessMapper;
    }

    /**
     * @param  RoleAccessMapper $roleAccessMapper
     *
     * @return $this
     */
    public function setRoleAccessMapper($roleAccessMapper)
    {
        $this->roleAccessMapper = $roleAccessMapper;
        return $this;
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param  External_UserInterface $externalDb
     * @return $this
     */
    public function setExternalDb($externalDb)
    {
        $this->externalDb = $externalDb;
        return $this;
    }

    /**
     * @return External_UserInterface
     */
    public function getExternalDb()
    {
        return $this->externalDb;
    }
}
