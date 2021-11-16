<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\KeyChain;

use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepository;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainRepositoryInterface;
use OverflowException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\LTI\Provider\Model as ToolModel;

/**
 * Keychain repository factory for the Schoolbox LTI platform: builds a keychain
 * for the platform itself, and for all tools registered within it.
 */
class RepositoryFactory
{
    /**
     * Reserved name for this platform's keychain.
     *
     * Tools may not use this as their client ID, as the client ID doubles as
     * the keychain identifier.
     */
    public const PLATFORM_KEYCHAIN_IDENTIFIER = 'schoolbox';

    /**
     * Name of this platform's key set.
     *
     * AFAIK this doesn't have to be unique?
     */
    public const PLATFORM_KEYCHAIN_KEY_SET_NAME = 'schoolbox-lti-platform';

    /**
     * Configuration: contains the platform keychain's key pair.
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Build the keychain repository: one for the platform, and one each for
     * each LTI tool.
     *
     * @param iterable<ToolModel> $tools
     * @return KeyChainRepositoryInterface
     * @throws OverflowException if any tools share the same client ID, or have
     *                           the reserved name `self::PLATFORM_KEYCHAIN_IDENTIFIER`
     *                           as their client ID
     */
    public function build(iterable $tools): KeyChainRepositoryInterface
    {
        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory    = new KeyChainFactory();
        $keyChainRepository = new KeyChainRepository();

        // Create the platform keychain
        $keyChainRepository->addKeyChain(
            $keyChainFactory->create(
                self::PLATFORM_KEYCHAIN_IDENTIFIER,
                self::PLATFORM_KEYCHAIN_KEY_SET_NAME,
                $this->config->get(ConfigMapper::NAME_JWT_PUBLIC_KEY),
                $this->config->get(ConfigMapper::NAME_JWT_PRIVATE_KEY),
            )
        );
        foreach ($tools as $tool) {
            $identifier = $tool->getDeploymentId();
            if (!isset($identifier)) {
                continue;
            }
            if ($keyChainRepository->find($identifier)) {
                throw new OverflowException(
                    sprintf('Key with identifier "%s" already exists: change that tool\'s client ID', $identifier)
                );
            }
            $keyChainRepository->addKeyChain(
                $keyChainFactory->create(
                    $tool->getDeploymentId(),
                    $tool->getName(),
                    $tool->getPublicKey() === null ? null : trim($tool->getPublicKey()),
                )
            );
        }
        return $keyChainRepository;
    }
}
