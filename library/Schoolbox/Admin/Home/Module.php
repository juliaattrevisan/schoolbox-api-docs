<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

use Alaress\Authorizer\Authorizer;
use Alaress\Database;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    protected $basename = 'adminHome';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, "");
        $this->addGetRoute($router, 'getHealthcheck', $basePath, "/healthcheck");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     * @param Container|ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'kamiConnector'    => $this->getInjector()->lazyGet('kamiConnector'),
            'databaseFactory'  => $this->getInjector()->lazyGet('databaseFactory'),
            'databaseExternal' => $injector->lazy(static function () use ($injector) {
                try {
                    return $injector->get('databaseExternal');
                } catch (Database\Exception $e) {
                    return null;
                }
            }),
        ]));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
