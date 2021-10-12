<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Registration;

use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Tool\Tool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LTI\Provider\Model as ToolModel;
use Schoolbox\Url\Factory as UrlFactory;

/**
 * Registration repository factory for the Schoolbox LTI platform.
 */
class RepositoryFactory
{
    /**
     * Configuration: required to build a base URL for the repository.
     */
    private IConfigLoader $config;

    /**
     * The OIDC authentication path (relative to the Schoolbox instance)
     * for the Schoolbox platform.
     */
    private string $oidcAuthenticationPath;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     * @param string        $oidcAuthenticationPath
     */
    public function __construct(IConfigLoader $config, string $oidcAuthenticationPath)
    {
        $this->config                 = $config;
        $this->oidcAuthenticationPath = $oidcAuthenticationPath;
    }

    /**
     * Build the registration repository
     *
     * @param iterable|ToolModel[] $tools
     * @return Repository
     */
    public function build(iterable $tools): Repository
    {
        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory = new KeyChainFactory();

        // The platform keychain needs both a private and public key:
        // * the chain factory requires public keys
        // * the platform will decipher messages sent by the tool, signed by its
        //   own public key
        $platformKeyChain = $keyChainFactory->create(
            '1',
            'schoolboxPlatform',
            $this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
            $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
        );

        // Configure a platform
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-platform
        $baseUrl  = (new UrlFactory($this->config))->buildFullyQualified()->setScheme('https');
        $platform = new Platform(
            'schoolbox',
            'Schoolbox Platform',
            $baseUrl->toString(),
            (clone $baseUrl)->setPath($this->oidcAuthenticationPath)->toString(),
        );

        // Build a registration out of each tool
        $registrations = [];
        foreach ($tools as $toolModel) {
            // Configure a tool
            // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
            $tool = new Tool(
                $toolModel->getName(),
                $toolModel->getName(),
                $toolModel->getAudience(),
                $toolModel->getOidcInitiationUrl(),
                $toolModel->getLaunchUrl(),
                $toolModel->getDeepLinkingUrl()
            );

            // Configure a registration
            // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-registration
            $registrations[] = new Registration(
                $toolModel->getDeploymentId(),
                $toolModel->getClientId(),
                $platform,
                $tool,
                [$toolModel->getDeploymentId(),],
                $platformKeyChain,
                $keyChainFactory->create(
                    $toolModel->getName(),
                    $toolModel->getName(),
                    $toolModel->getPublicKey()
                ),
                null,
                $toolModel->getJwksUrl()
            );
        }
        return new Repository(...$registrations);
    }
}
