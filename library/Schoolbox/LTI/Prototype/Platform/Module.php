<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype\Platform;

use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\UserMapper;

/**
 * LTI prototype platform module
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiPrototypePlatform';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $this->register(self::METHOD_GET, '', 'getIndex', new IsLoggedIn());
        $this->register(self::METHOD_GET, '/generate-resource-link-request', 'getGenerateResourceLinkRequest');
        $this->register(self::METHOD_GET, '/oidc/authentication', 'getOidcAuthenticationRequest');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                ['userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class)]
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
