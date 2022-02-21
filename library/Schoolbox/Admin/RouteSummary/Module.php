<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\RouteSummary;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;

/**
 * A module for viewing a list of routes defined within Schoolbox.
 */
class Module extends BaseModule
{
    protected $basename = 'adminRouteSummary';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $this->register('GET', "", 'getRouteList', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
