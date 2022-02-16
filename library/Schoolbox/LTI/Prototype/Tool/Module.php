<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Tool;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;

/**
 * LTI prototype tool module
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiPrototypeTool';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $this->register(self::METHOD_GET, '/oidc/initiation', 'getOidcInitiationRequest');
        $this->register(self::METHOD_GET, '/launch', 'getLaunch');
        $this->register(self::METHOD_POST, '/oidc/initiation', 'postOidcInitiationRequest');
        $this->register(self::METHOD_POST, '/launch', 'postLaunch');
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
