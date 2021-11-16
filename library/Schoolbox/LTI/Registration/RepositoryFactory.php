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
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use OAT\Library\Lti1p3Core\Tool\Tool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LTI\KeyChain\RepositoryFactory as KeyChainRepositoryFactory;
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
     * Key chain repository: contains the platform's key chain, and the key
     * chain for each tool that could be set up.
     */
    private KeyChainRepositoryInterface $keyChainRepository;

    /**
     * The OIDC authentication path (relative to the Schoolbox instance)
     * for the Schoolbox platform.
     */
    private string $oidcAuthenticationPath;

    /**
     * The JSON web key set path (relative to the Schoolbox instance)
     * for the Schoolbox platform.
     */
    private string $jsonWebKeySetPath;

    /**
     * Constructor
     *
     * @param IConfigLoader               $config
     * @param KeyChainRepositoryInterface $keyChainRepository
     * @param string                      $oidcAuthenticationPath
     * @param string                      $jsonWebKeySetPath
     */
    public function __construct(
        IConfigLoader $config,
        KeyChainRepositoryInterface $keyChainRepository,
        string $oidcAuthenticationPath,
        string $jsonWebKeySetPath
    ) {
        $this->config                 = $config;
        $this->keyChainRepository     = $keyChainRepository;
        $this->oidcAuthenticationPath = $oidcAuthenticationPath;
        $this->jsonWebKeySetPath      = $jsonWebKeySetPath;
    }

    /**
     * Build the registration repository
     *
     * @param iterable|ToolModel[] $tools
     * @return Repository
     */
    public function build(iterable $tools): Repository
    {
        // Configure a platform
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-platform
        $baseUrl  = (new UrlFactory($this->config))->buildFullyQualified()->setScheme('https');
        $platform = new Platform(
            'schoolbox',
            'Schoolbox Platform',
            $baseUrl->toString(),
            (clone $baseUrl)->setPath($this->oidcAuthenticationPath)->toString(),
        );

        $jsonWebKeySetPath = (clone $baseUrl)->setPath($this->jsonWebKeySetPath)->toString();

        // Build a registration out of each tool
        $registrations = [];
        foreach ($tools as $toolModel) {
            // Configure a tool
            // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
            $oidcInitiationUrl = $toolModel->getOidcInitiationUrl();
            if ($oidcInitiationUrl === null) {
                continue;
            }
            $tool = new Tool(
                $toolModel->getName(),
                $toolModel->getName(),
                // Tool audience is only required when acting as the tool,
                // and Schoolbox is acting as the platform
                '',
                trim($oidcInitiationUrl),
                $toolModel->getLaunchUrl() === null ? null : trim($toolModel->getLaunchUrl()),
                $toolModel->getDeepLinkingUrl() === null ? null : trim($toolModel->getDeepLinkingUrl()),
            );

            // Configure a registration
            // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-registration
            $registrations[] = new Registration(
                $toolModel->getDeploymentId(),
                $toolModel->getDeploymentId(),
                $platform,
                $tool,
                [$toolModel->getDeploymentId(),],
                $this->keyChainRepository->find(KeyChainRepositoryFactory::PLATFORM_KEYCHAIN_IDENTIFIER),
                $this->keyChainRepository->find($toolModel->getDeploymentId()),
                $jsonWebKeySetPath,
                $toolModel->getJwksUrl() === null ? null : trim($toolModel->getJwksUrl()),
            );
        }
        return new Repository(...$registrations);
    }
}
