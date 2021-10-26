<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\LTI\Provider\Mapper as ToolMapper;

/**
 * LTI platform module.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiPlatform';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // TODO build a resource link launch request for the given tool

        // Build a deep linking request for the given tool
        $preloadTool = function ($params) {
            $toolMapper = $this->getMapperFactory()->get(ToolMapper::class);
            return [
                'tool' => $toolMapper->findById($params['toolId']),
            ];
        };
        $this->register(
            self::METHOD_GET,
            "/deep-linking-request/{toolId}",
            'getGenerateDeepLinkingRequest',
            null,
            $preloadTool,
        );

        // TODO add JWKS endpoint?

        // OIDC authentication
        $this->register(self::METHOD_GET, '/oidc/authentication', 'getOidcAuthenticationRequest');

        // Receive a response to a deep linking request
        $this->register(
            self::METHOD_GET,
            "/deep-linking-response/{toolId}",
            'getReceiveDeepLinkingResponse',
            null,
            $preloadTool,
        );
        $this->register(
            self::METHOD_POST,
            "/deep-linking-response/{toolId}",
            'postReceiveDeepLinkingResponse',
            null,
            $preloadTool,
        );
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'repository'        => $injector->lazyGet('lti.repository'),
                    'userAuthenticator' => $injector->lazyGet('lti.userAuthenticator'),
                    'instance'          => $injector->lazyGet('instance'),
                ]
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
