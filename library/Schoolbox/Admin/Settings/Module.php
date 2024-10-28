<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Alaress\Authorizer\Rule;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'settings';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $ruleSearchSettings = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL),
            )
        );

        $rulePastoral = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)
            )
        );

        $ruleSuperuserOnly = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        // Routes to which users with service permission UserAccess::SERVICE_PASTORAL need access.
        $this->register('GET', '/pastoral/{typeLabel:Pastoral%20Care}', 'getSettingsPastoral', $rulePastoral);
        $this->register('POST', '/pastoral/{typeLabel:Pastoral%20Care}', 'postSettingsPastoral', $rulePastoral);

        $this->register('GET', '/search', 'searchSettings', $ruleSearchSettings);
        $this->register('GET', '{/typeLabel}', 'getSettings', $ruleSuperuserOnly);
        $this->register('POST', '/{typeLabel}', 'postSettings', $ruleSuperuserOnly);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
        );
    }
}
