<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Log\Mapper as LogMapper;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;
use Schoolbox\Version\Version;

class Module extends BaseModule
{
    protected $basename = 'adminHome';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('getIndex', $basePath, "");
        $this->addGetRoute('getSslRenewalHelp', $basePath, "/ssl-renewal-help");
        $this->addGetRoute('getHealthcheck', $basePath, "/healthcheck");
        $this->addGetRoute('getDisasterRecovery', $basePath, "/disaster-recovery");
        $this->addGetRoute('dismissUpgradeReminder', $basePath, "/dismiss-upgrade-reminder");
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
             )
            ->addRoutePermission(
                $authorizer,
                'getSslRenewalHelp',
                new Rule\Callback(function () {
                    // Page not accessible in cloud
                    if ($this->getConfig()->get('site_environment') === Version::SITE_ENVIRONMENT_CLOUD_SAAS) {
                        throw new InvalidPath();
                    }
                })
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
