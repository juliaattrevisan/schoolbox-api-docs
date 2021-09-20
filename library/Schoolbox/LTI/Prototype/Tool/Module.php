<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Tool;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;

/**
 * LTI test tool module
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
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
