<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Prototype;

use Laminas\Uri\UriInterface;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Tool\Tool;

/**
 * Registration repository for the prototype platform and tool.
 */
class RegistrationRepository implements RegistrationRepositoryInterface
{
    /**
     * Registration's unique identifier
     */
    public const REGISTRATION_IDENTIFIER = 'schoolboxVagrant';

    /**
     * Registration's client ID
     */
    public const REGISTRATION_CLIENT_ID = 'schoolboxVagrantRegistration';

    /**
     * Registration's deployment IDs
     */
    public const REGISTRATION_DEPLOYMENT_IDS = ['deploymentId'];

    /**
     * The base URL for the tool and platform.
     *
     * Used as the audience for the tool and platform, and also as the base for
     * their URLs.
     */
    private UriInterface $baseUrl;

    /**
     * The public key used by both the tool and platform.
     */
    private string $sharedPublicKey;

    /**
     * The private key used by both the tool and platform.
     */
    private string $sharedPrivateKey;

    /**
     * The registration.
     */
    private Registration $registration;

    /**
     * Constructor
     *
     * @param UriInterface $baseUrl
     * @param string       $sharedPublicKey
     * @param string       $sharedPrivateKey
     */
    public function __construct(UriInterface $baseUrl, string $sharedPublicKey, string $sharedPrivateKey)
    {
        $this->baseUrl          = $baseUrl;
        $this->sharedPublicKey  = $sharedPublicKey;
        $this->sharedPrivateKey = $sharedPrivateKey;
        $this->registration     = $this->buildTestRegistration();
    }

    /**
     * Find the registration by its identifier.
     *
     * @param string $identifier
     * @return RegistrationInterface|null
     */
    public function find(string $identifier): ?RegistrationInterface
    {
        return $this->registration->getIdentifier() === $identifier
            ? $this->registration
            : null;
    }

    /**
     * Find all registrations: in this repo, there is only one registration.
     *
     * @return Registration[]
     */
    public function findAll(): array
    {
        return [$this->registration];
    }

    /**
     * Find the registration by its client ID,
     *
     * @param string $clientId
     * @return RegistrationInterface|null
     */
    public function findByClientId(string $clientId): ?RegistrationInterface
    {
        return $this->registration->getClientId() === $clientId
            ? $this->registration
            : null;
    }

    /**
     * Find the registration by its platform issuer (a.k.a. audience), and
     * optionally its client ID.
     *
     * @param string      $issuer
     * @param string|null $clientId
     * @return RegistrationInterface|null
     */
    public function findByPlatformIssuer(string $issuer, string $clientId = null): ?RegistrationInterface
    {
        if ($this->registration->getPlatform()->getAudience() === $issuer) {
            if (!isset($clientId) || $this->registration->getClientId() === $clientId) {
                return $this->registration;
            }
        }
        return null;
    }

    /**
     * Find the registration by its tool issuer (a.k.a. audience), and
     * optionally its client ID.
     *
     * @param string      $issuer
     * @param string|null $clientId
     * @return RegistrationInterface|null
     */
    public function findByToolIssuer(string $issuer, string $clientId = null): ?RegistrationInterface
    {
        if ($this->registration->getTool()->getAudience() === $issuer) {
            if (!isset($clientId) || $this->registration->getClientId() === $clientId) {
                return $this->registration;
            }
        }
        return null;
    }

    /**
     * Build the registration.
     *
     * @return Registration
     */
    private function buildTestRegistration(): Registration
    {
        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory = new KeyChainFactory();

        // Tool and platform keychains both need both a private and public key,
        // as this provides info for both the tool and platform
        $toolKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $this->sharedPublicKey,
            $this->sharedPrivateKey,
        );
        $platformKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $this->sharedPublicKey,
            $this->sharedPrivateKey,
        );

        // Configure a platform
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-platform
        $platform = new Platform(
            'schoolboxVagrantPlatform',
            'Schoolbox [vagrant] Platform',
            $this->baseUrl->toString(),
            (clone $this->baseUrl)->setPath('/lti-prototype/platform/oidc/authentication')->toString(),
        );

        // Configure a tool
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
        $tool = new Tool(
            'schoolboxVagrantTool',
            'Schoolbox [vagrant] Tool',
            $this->baseUrl->toString(),
            (clone $this->baseUrl)->setPath('/lti-prototype/tool/oidc/initiation')->toString(),
        );

        // Configure a registration
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-registration
        return new Registration(
            self::REGISTRATION_IDENTIFIER,
            self::REGISTRATION_CLIENT_ID,
            $platform,
            $tool,
            self::REGISTRATION_DEPLOYMENT_IDS,
            $platformKeyChain,
            $toolKeyChain,
        );
    }
}
