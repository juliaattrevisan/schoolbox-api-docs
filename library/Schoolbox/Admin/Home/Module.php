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
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Log\Mapper as LogMapper;
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
        $this->addGetRoute($router, 'getSslRenewalHelp', $basePath, "/ssl-renewal-help");
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('databaseFactory'),
            $this->injector->get('databaseExternal'),
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(LogMapper::class),
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
