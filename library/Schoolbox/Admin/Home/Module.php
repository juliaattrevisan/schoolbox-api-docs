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
use Alaress\Authorizer\Rule;
use Alaress\Database;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;

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
        $this->addGetRoute($router, 'getDisasterRecovery', $basePath, "/disaster-recovery");
        $this->addGetRoute($router, 'dismissUpgradeReminder', $basePath, "/dismiss-upgrade-reminder");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser())
             ->addRoutePermission(
                 $authorizer,
                 'getDisasterRecovery',
                 new Rule\All(
                     new Rule\IsSuperuser(),
                     new Rule\IsConfigOn(DisasterRecovery::CONFIG_DR_VISIBILITY, $this->getConfig())
                 )
             );
    }

    /**
     * {@inheritdoc}
     * @param Container|ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'configMapper'     => $this->getMapperFactory()->getLazy(ConfigMapper::class),
            'kamiConnector'    => $this->injector->lazyGet('kamiConnector'),
            'databaseFactory'  => $this->injector->lazyGet('databaseFactory'),
            'databaseExternal' => $injector->lazyGet('databaseExternal'),
//            'databaseExternal' => $injector->lazy(
//                static function () use ($injector) {
//                    try {
//                        return $injector->get('databaseExternal');
//                    } catch (Database\Exception $e) {
//                        return null;
//                    }
//                }
//            ),
        ]));
    }

    /**
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): ?Base
    {
        return $this->installControllerDependencies(
            new Controller(
                $this->injector->get('kamiConnector'),
                $this->injector->get('databaseFactory'),
                $this->injector->get('databaseExternal'),
                $this->getMapperFactory()->get(ConfigMapper::class)
            ),
            $container,
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
