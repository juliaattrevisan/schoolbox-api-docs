<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\TestPlatform;

use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Tool\Tool;

/**
 * Registration repository for the test platform.
 */
class RegistrationRepository implements RegistrationRepositoryInterface
{
    /**
     * Audience of the test tool
     */
    public const TOOL_AUDIENCE = 'http://devkit-lti1p3.localhost/tool';

    /**
     * Tool's OIDC initiation URL: the platform will send an authorization
     * request to this URL
     */
    public const TOOL_OIDC_INITIATION_URL = 'http://devkit-lti1p3.localhost/lti1p3/oidc/initiation';

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
    public const REGISTRATION_DEPLOYMENT_IDS = ['deploymentId3'];

    /**
     * The test registration.
     */
    private Registration $registration;

    /**
     * Constructor
     *
     * @param string $platformAudience The audience for this repo's
     *                                 registration's platform: should be the
     *                                 same as the platform's base URL.
     */
    public function __construct(string $platformAudience)
    {
        $this->registration = $this->buildTestRegistration($platformAudience);
    }

    /**
     * Find the test registration by its identifier.
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
     * Find all registrations: in this repo, it is only the test registration.
     *
     * @return Registration[]
     */
    public function findAll(): array
    {
        return [$this->registration];
    }

    /**
     * Find the test registration by its client ID,
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
     * Find the test registration by its platform issuer (a.k.a. audience),
     * and optionally its client ID.
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
     * Find the test registration by its tool issuer (a.k.a. audience),
     * and optionally its client ID.
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
     * Build the test registration.
     *
     * @param string $platformAudience
     * @return Registration
     */
    private function buildTestRegistration(string $platformAudience): Registration
    {
        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory = new KeyChainFactory();
        $sharedPublicKey = <<<PUBLICKEY
            -----BEGIN PUBLIC KEY-----
            MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyZXlfd5yqChtTH91N76V
            okquRu2r1EwNDUjA0GAygrPzCpPbYokasxzs+60Do/lyTIgd7nRzudAzHnujIPr8
            GOPIlPlOKT8HuL7xQEN6gmUtz33iDhK97zK7zOFEmvS8kYPwFAjQ03YKv+3T9b/D
            brBZWy2Vx4Wuxf6mZBggKQfwHUuJxXDv79NenZarUtC5iFEhJ85ovwjW7yMkcflh
            Ugkf1o/GIR5RKoNPttMXhKYZ4hTlLglMm1FgRR63pvYoy9Eq644a9x2mbGelO3Hn
            GbkaFo0HxiKbFW1vplHzixYCyjc15pvtBxw/x26p8+lNthuxzaX5HaFMPGs10rRP
            LwIDAQAB
            -----END PUBLIC KEY-----
            PUBLICKEY;
        $sharedPrivateKey = <<<PRIVATEKEY
            -----BEGIN RSA PRIVATE KEY-----
            MIIEowIBAAKCAQEAyZXlfd5yqChtTH91N76VokquRu2r1EwNDUjA0GAygrPzCpPb
            Yokasxzs+60Do/lyTIgd7nRzudAzHnujIPr8GOPIlPlOKT8HuL7xQEN6gmUtz33i
            DhK97zK7zOFEmvS8kYPwFAjQ03YKv+3T9b/DbrBZWy2Vx4Wuxf6mZBggKQfwHUuJ
            xXDv79NenZarUtC5iFEhJ85ovwjW7yMkcflhUgkf1o/GIR5RKoNPttMXhKYZ4hTl
            LglMm1FgRR63pvYoy9Eq644a9x2mbGelO3HnGbkaFo0HxiKbFW1vplHzixYCyjc1
            5pvtBxw/x26p8+lNthuxzaX5HaFMPGs10rRPLwIDAQABAoIBAEFrF7KlQeg4VmAA
            7PVOMBkeyzfRYXXfyEyLU6dL0JiB9Vl1ajir7bI6rN1v5EKObP1RHwWHXRbr7ery
            jJnjWXRqV2mhDjBseAEIVhpYy6UNWLSBUZ7njiPV/wQaBr2Stq8ydfLKeZpmME7g
            y2xw8uc064qfXIAHbhRq82yAN8+YY7CUKBvJquiiRVDffvbYWhrS4fIIsBNywoBZ
            PGoKUhE9RlR5nRyWvQmeKsBmM9bbzEIW4A5GHO5+6Fb2g1cbLx1poCZRta2oE/JJ
            qusChTZMXdGmEapT8w+sLqyZYtyPn+W8MuX5N9oZppYWIzUDlxHn6yqQPOj98aKu
            rsIu+aECgYEA+x047lp1C3R5dByfk9JwxTayTgfyrnCElV60+KVYxwJkZniMkBk7
            ncy0WtcOBQiwNYLCHMpx9IZkSlWbW0C3e2CgGmLahpi8yhBOzPiUinLY/V5xzbxK
            ojTkf3pYZo1viJG9MTUI1xEr9anZHM9GmqVnk8gJdi2a64wXGyvXxiUCgYEAzYH5
            8oRuvWgT+4KXql8EUX57YQEDIpeqp4DJHMk5X5OWYZ8by5kyRNDLSvqf5kWnkRpK
            VMXuKdpVJXb3OWBvzymzxB/pBfTFFaBybsMQZUKpXfucRSfeQ0T6x1VVLozb6mNH
            T7q4nms1LiUidp7M09XtfwJYMlLu/prZ1yN+jcMCgYEA78e4nAiuKNEARDosumdA
            nWAcJFx8g8sXHtY/MgoY2nbTTfGgLtyZS5WzReaCEZZ5Q69nETzSUW6eh0h1P05t
            pZbfajKofcuMwdmOlTRsCkOeJVmwi4ZXMcoVwhAeJ1a4gIzBPiJpHYvdEQgtM9BM
            l9CHNdrWBg2IF5E+YwibUi0CgYBMKtpa2l648LRHSbWwvZq6IajU4S0qSxBDGOZx
            Ntt+4xKfh/sjUNpiywgt+An/rN1YWGgoV1vYQ0W/pwImT+ng3FH2ZOVXActyIo+H
            IeEXxsdDQBhBQW+Neyl/a88we6CelME/ebMndBC306ecU2sTMHzf0BykOjy+POJ0
            bIFhRQKBgBjfK/s6mbif311d4kEzT0aGIPK0STfD5FKDCazdZ3F+/aS8iorEVGou
            T7EwvdJvb13N9CpQGvN1nbSUc+rI3MY/Fa0m4FxE1A0FnS3AAVISgY3M4k2pjbIa
            3LfQHtOPDKX0gaYFqf99w1Xwtib4E5PFaRcdUuuc1o8Xv315uh8p
            -----END RSA PRIVATE KEY-----
            PRIVATEKEY;


        // Tool keychain needs only the public key: I don't know its private key
        $toolKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $sharedPublicKey,
        );

        // OTOH: I am the platform, I need to know my own private key
        $platformKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $sharedPublicKey,
            $sharedPrivateKey,
        );

        // Configure a platform
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-platform
        $platform = new Platform(
            'schoolboxVagrantPlatform',
            'Schoolbox [vagrant]',
            $platformAudience,
        );

        // Configure a tool
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
        $tool = new Tool(
            'devkitTool',
            'LTI 1.3 DevKit (as tool)',
            self::TOOL_AUDIENCE,
            self::TOOL_OIDC_INITIATION_URL,
        );

        // Configure a registration
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-registration
        // Need to set this up in the devkit
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
