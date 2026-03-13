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

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;

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
        $this->register('GET', '/configuration/api/{categoryId}', 'getConfigurationData', $ruleIBCoordinatorAccess);
        $this->register('POST', '/configuration/api/save', 'saveConfiguration', $ruleIBCoordinatorAccess);
        $this->register('GET', '/registrations', 'getRegistrations', $ruleIBCoordinatorAccess);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
