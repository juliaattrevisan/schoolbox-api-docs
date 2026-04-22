<?php

/**
 * International Baccalaureate admin module
 *
 * @author      Haris Ali <haris.ali@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Subject\Language\Mapper as IBGroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Subject\Mapper as IBGroupSubjectMapper;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class Module extends BaseModule
{
    protected $basename = 'internationalBaccalaureate';

    /**
     * @return void
     */
    protected function registerRoutes(): void
    {
        $ruleIBCoordinatorAccess = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_IB_COORDINATOR, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('GET', '/configuration', 'getConfiguration', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/subjects/{groupId:\\d+}', 'getCategorySubjects', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/categories/{categoryId:\\d+}', 'getConfigurationData', $ruleIBCoordinatorAccess);
        $this->register('GET', '/configuration/api/languages', 'searchLanguages', $ruleIBCoordinatorAccess);
        $this->register('POST', '/configuration/api/save', 'saveConfiguration', $ruleIBCoordinatorAccess);
        $this->register('GET', '/registrations', 'getRegistrations', $ruleIBCoordinatorAccess);

        $this->register('GET', '/registrations/sessions', 'getRegistrationSessions', $ruleIBCoordinatorAccess);
        $this->register('POST', '/registrations/sessions', 'createRegistrationsSession', $ruleIBCoordinatorAccess);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(IBGroupMapper::class),
            $this->getMapperFactory()->get(IBGroupSubjectMapper::class),
            $this->getMapperFactory()->get(IBGroupSubjectLanguageMapper::class),
            $this->getMapperFactory()->get(LanguageMapper::class),
            new FieldStrategyFactory($this->getMapperFactory(), new CollectorFactory()),
            $this->injector->get('moduleCache.rebuilder'),
            $this->injector->get('oidc.cache'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
