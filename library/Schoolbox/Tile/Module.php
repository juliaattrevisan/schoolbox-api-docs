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
use Schoolbox\Resource\CategoryMapper;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
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
     * Dependency on external user database.
     *
     * Required for checking that the requesting user is a teacher of the class
     * homepage that this component is on.
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->addGet('tile.layout', $basePath.'/layout/{componentId}');
        $router->addPost('tile.layoutForm', $basePath.'/layoutForm/{componentId}');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the component to params, as it's used in permission checks
        $mapper = $this->getComponentInstanceMapper();
        $matcher->add(
            '/^tile\\.(?:layout|layoutForm)$/',
            function ($params) use ($mapper) {
                $component = array_pop($mapper->findByInstanceId($params['componentId']));
                if (!($component instanceof Component_Instance)) {
                    // error out.
                    // This should cause an error that is recorded, but doesn't
                    // prevent a login check.
                }
                return array(
                    'component' => $component,
                );
            }
        );
    }

//    /**
//     * {@inheritdoc}
//     */
//    public function initRoutePermissions(Authorizer $authorizer)
//    {
//        // Layouts:
//        // editable by anyone who has write permission on the homepage
//        // this can include teachers?
//        $rule = new Rule\Any(
//            new Rule\IsSuperuser(),
//            new Rule\HasServicePermission(
//                UserAccess::SERVICE_HOMEPAGE,
//                UserAccess::ACCESS_WRITE
//            )
//        );
////        $authorizer
////            ->addRule('/^resourceCategory(?:$|\.)/', new Rule\IsLoggedIn())
////            ->addRule('/^resourceCategory\.(?:add|edit|delete)/', $resourceManageRule);
//    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__ . '/templates', 'tile');
        $templateLoader->addLoader($loader);
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
        $injector->set(
            'controller:tile',
            $injector->lazyNew('Schoolbox\\Tile\\Controller')
        );
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
