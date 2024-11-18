<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

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
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $ruleSuperuser = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        $ruleSuperuserOrModerator = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_BADGE, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_TIMETABLE, UserAccess::ACCESS_FULL),
            )
        );

        $ruleDisasterRecovery = new Rule\All(
            new Rule\IsSuperuser(),
            new Rule\IsConfigOn(DisasterRecovery::CONFIG_DR_VISIBILITY, $this->getConfig())
        );

        $this->register('GET', '', 'getIndex', $ruleSuperuserOrModerator);
        $this->register('GET', '/healthcheck', 'getHealthcheck', $ruleSuperuserOrModerator);
        $this->register('GET', '/disaster-recovery', 'getDisasterRecovery', $ruleDisasterRecovery);
        $this->register('GET', '/dismiss-upgrade-reminder', 'dismissUpgradeReminder', $ruleSuperuser);
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
