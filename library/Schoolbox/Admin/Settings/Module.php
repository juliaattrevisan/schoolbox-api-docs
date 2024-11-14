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
        $ruleSuperuserOnly = new Rule\All(
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
            )
        );

        $rulePastoral = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)
            )
        );

        $ruleLms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        $ruleCms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
            )
        );

        $ruleDesign = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
            )
        );

        // Routes to which users with service permission UserAccess::SERVICE_PASTORAL need access.
        $this->register('GET', '/pastoral/{typeLabel:Pastoral\+Care}', 'getSettingsPastoral', $rulePastoral);
        $this->register('POST', '/pastoral/{typeLabel:Pastoral\+Care}', 'postSettingsPastoral', $rulePastoral);

        // Routes to which users with service permission UserAccess::SERVICE_LMS need access.
        $lmsTypeLabelsRegex = '(Document\+Management|LMS\+Policy)';
        $this->register('GET', "/lms/{typeLabel:$lmsTypeLabelsRegex}", 'getSettingsLms', $ruleLms);
        $this->register('POST', "/lms/{typeLabel:$lmsTypeLabelsRegex}", 'postSettingsLms', $ruleLms);

        // Routes to which users with service permission UserAccess::SERVICE_CMS need access.
        $this->register('GET', '/cms/{typeLabel:Resources}', 'getSettingsCms', $ruleCms);
        $this->register('POST', '/cms/{typeLabel:Resources}', 'postSettingsCms', $ruleCms);

        // Routes to which users with service permission UserAccess::SERVICE_DESIGN need access.
        $this->register('GET', '/design/{typeLabel:Skin\+Config}', 'getSettingsDesign', $ruleDesign);
        $this->register('POST', '/design/{typeLabel:Skin\+Config}', 'postSettingsDesign', $ruleDesign);

        // Routes to which all moderators as well as superusers need access.
        $this->register('GET', '/search', 'searchSettings', $ruleSuperuserOrModerator);

        // Routes to which only superusers need access.
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
