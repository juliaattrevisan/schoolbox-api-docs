<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth\Client;

use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for displaying frontend for setting up oAuth clients
 * (a.k.a. OpenID Connect relying parties) in Schoolbox's admin system.
 */
class Module extends BaseModule
{
    protected $basename = 'adminOauthClient';

    /**
     * Register frontend route for showing oAuth clients.
     */
    protected function registerRoutes()
    {
        $this->register(self::METHOD_GET, '', 'getIndex', new IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Controller
    {
        return new Controller();
    }
}
