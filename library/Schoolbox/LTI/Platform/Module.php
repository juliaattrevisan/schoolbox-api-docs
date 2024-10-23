<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform;

use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
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

        // Deep linking request has been moved to context controller

        $preloadTool = function ($params) {
            $toolMapper = $this->getMapperFactory()->get(ToolMapper::class);
            return [
                'tool' => $toolMapper->findById($params['toolId']),
            ];
        };

        // OIDC authentication
        $this->register(self::METHOD_GET, '/oidc/authentication', 'getOidcAuthenticationRequest');
        $this->register(self::METHOD_POST, '/oidc/authentication', 'postOidcAuthenticationRequest');

        // oAuth access token
        $this->register(self::METHOD_GET, '/auth/token', 'getOauthAccessTokenRequest');
        $this->register(self::METHOD_POST, '/auth/token', 'postOauthAccessTokenRequest');

        // JWKS
        $this->register(self::METHOD_GET, '/.well-known/jwks.json', 'getJsonWebKeySet');

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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('lti.keyChains'),
            $this->injector->get('lti.registrations'),
            $this->injector->get('lti.userAuthenticator'),
            $this->injector->get('lti.accessTokenCache'),
            $this->getMapperFactory()->get(ToolMapper::class),
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
