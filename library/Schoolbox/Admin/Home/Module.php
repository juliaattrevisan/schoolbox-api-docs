<?php

/**
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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Log\Mapper as LogMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;

class Module extends BaseModule
{
    protected $basename = 'adminHome';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('getIndex', $basePath, "");
        $this->addGetRoute('getHealthcheck', $basePath, "/healthcheck");
        $this->addGetRoute('getDisasterRecovery', $basePath, "/disaster-recovery");
        $this->addGetRoute('dismissUpgradeReminder', $basePath, "/dismiss-upgrade-reminder");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new Rule\HasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL),
                    new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
                    new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL),
                    new Rule\HasServicePermission(UserAccess::SERVICE_BADGE, UserAccess::ACCESS_FULL),
                )
            )
        );

        $this->addRoutePermission(
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
