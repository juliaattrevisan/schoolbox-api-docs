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
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\LTI\Registration;

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
        // Test sending a msg back to the main form
        $this->register(self::METHOD_GET, "", 'getIndex');

        // Build a resource link launch request for the given registration

        // Build a deep linking request for the given registration
        $this->register(
            self::METHOD_GET,
            "/deep-linking-request/{toolId}",
            'getGenerateDeepLinkingRequest',
            null,
            function ($params) {
                $toolMapper = $this->getMapperFactory()->get(ToolMapper::class);
                return [
                    'tool' => $toolMapper->findById($params['toolId']),
                ];
            }
        );

        // JWKS URL?

        // OIDC authentication
        $this->register(self::METHOD_GET, '/oidc/authentication', 'getOidcAuthenticationRequest');

        // Response to deep linking requests
        $this->register(self::METHOD_GET, "/deep-linking-response", 'getReceiveDeepLinkingResponse');
        $this->register(self::METHOD_POST, "/deep-linking-response", 'postReceiveDeepLinkingResponse');
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
                    'repository' => $injector->lazy(function () {
                        $registrationRepositoryFactory = new Registration\RepositoryFactory($this->getConfig());
                        return $registrationRepositoryFactory->build(
                            $this->getMapperFactory()->get(ToolMapper::class)
                                                     ->findByColumn('lti_version', ToolModel::LTI_VERSION_1_3),
                        );
                    }),
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
