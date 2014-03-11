<?php
/**
 * Catch-all class for defining modules.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Module;

use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Authorizer\Authorizer;
use Twig_Loader_Filesystem;

class Module {

    /**
     * The dependency injection container. All router
     *
     * @var ContainerInterface
     */
    protected $injector;

    public function __construct(ContainerInterface $injector) {
        $this->setInjector($injector);
    }

    /**
     * Inits module
     *
     * @param string $basePath The base URL for this module, used by initRoutes
     */
    public function init($basePath) {
        $injector = $this->getInjector();
        $this->initRoutes($injector->get('router'), $basePath);
        $this->initRoutePermissions($injector->get('authorizer'));
        $this->initTemplatePaths($injector->get('templateEngine:loader'));
        $this->initControllers($injector);
    }

    /**
     * Initializes any routes for this module, with a given base path.
     *
     * @param Router $router
     * @param string $basePath The base route for this module
     */
    public function initRoutes(Router $router, $basePath) {}

    /**
     * Initializes any permissions on routes for this module.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer) {}

    /**
     * Initializes template paths for this module.
     *
     * @param Twig_Loader_Filesystem $templateLoader
     */
    public function initTemplatePaths(Twig_Loader_Filesystem $templateLoader) {}

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector) {}

    /**
     * @param  ContainerInterface $injector
     * @return $this
     */
    public function setInjector(ContainerInterface $injector) {
        $this->injector = $injector;
        return $this;
    }

    /**
     * @return ContainerInterface
     */
    public function getInjector() {
        return $this->injector;
    }

}
