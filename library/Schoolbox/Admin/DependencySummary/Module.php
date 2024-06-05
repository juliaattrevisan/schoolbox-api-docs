<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DependencySummary;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;

/**
 * A module for viewing a list of services defined within Schoolbox's dependency
 * injection container.
 */
class Module extends BaseModule
{
    protected $basename = 'adminDependencySummary';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $this->register('GET', "", 'getDependencyList', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->injector);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
