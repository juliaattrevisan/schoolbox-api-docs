<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Test;

use Laminas\Uri\UriInterface;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Tool\Tool;

/**
 * Registration repository for the test platform and tool.
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
     */
    private UriInterface $baseUrl;


    /**
     * The test registration.
     */
    private Registration $registration;

    /**
     * Constructor
     *
     * @param UriInterface $baseUrl The current base URL: used as the audience
     *                              for the platform and tool, and also as the
     *                              base for their URLs.
     */
    public function __construct(UriInterface $baseUrl)
    {
        $this->baseUrl      = $baseUrl;
        $this->registration = $this->buildTestRegistration();
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
     * @return Registration
     */
    private function buildTestRegistration(): Registration
    {
        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory = new KeyChainFactory();
        $sharedPublicKey = <<<PUBLICKEY
            -----BEGIN PUBLIC KEY-----
            MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAs5BfD0/+IeYbzNJJbeib
            HDQsHb1jaNOrG63HtzM7H+K2I/X3b9NGQNFEkhQxMTwbVVkbWITFoXOZHR0vNAb8
            w04ZxPOgtk1pwFwgQ9ERiZgyE8hfkSVwe4URh88sC+A+zWja+6OtBamM/dAGuDki
            FzzzdRH1G++l4joa8KywqgZm9AY63jTHRkEBv96bIXSPYnYa3EOnnXG9loh1ArT2
            KE57d6G8rIkblEBB4h+Jv+rIemrlVjO1YuwynNCoCcWox090i6ejKe2OEc4FsP/x
            JYFXIIlay95laYzypfPbpo4qeCoCCqY8W2zsWXx409G0DcEzDUpvq9xj4xo1ozBz
            SQIDAQAB
            -----END PUBLIC KEY-----
            PUBLICKEY;
        $sharedPrivateKey = <<<PRIVATEKEY
            -----BEGIN RSA PRIVATE KEY-----
            MIIEowIBAAKCAQEAs5BfD0/+IeYbzNJJbeibHDQsHb1jaNOrG63HtzM7H+K2I/X3
            b9NGQNFEkhQxMTwbVVkbWITFoXOZHR0vNAb8w04ZxPOgtk1pwFwgQ9ERiZgyE8hf
            kSVwe4URh88sC+A+zWja+6OtBamM/dAGuDkiFzzzdRH1G++l4joa8KywqgZm9AY6
            3jTHRkEBv96bIXSPYnYa3EOnnXG9loh1ArT2KE57d6G8rIkblEBB4h+Jv+rIemrl
            VjO1YuwynNCoCcWox090i6ejKe2OEc4FsP/xJYFXIIlay95laYzypfPbpo4qeCoC
            CqY8W2zsWXx409G0DcEzDUpvq9xj4xo1ozBzSQIDAQABAoIBAC7jPv9HmwuNA6tb
            KdNqTr5xnSdy4bdotiB3I1RiK0krMPZ47tCjcY/RVT9zyFib4mNJ7ksR+G0tO0kP
            6tiCDpSNG0uDdm7EDh4tsDslJ5gvRrxFsTa8eP+4Apjc7xp2xs9qVuwolDCLUoag
            QHDwckdxIf1q3/8Yd8937rrzzPnIXBIeklLPdiPkJD0Jqbp+Hifr+0eY0svk1bej
            6Vrvt16cwt5QVIGknFZgbkeg1ciTcHnqQqviDODf3ZcX8gacIm1XUTzpooxcz+yA
            dxknRoPTLABzyVGYtnEUaJa11rwF6EcT1W9B3XMxhZxazqrWciUngeWZxS0SAgGm
            OLDrSvUCgYEA4Zf1ifJ+BW/JyQOWlMkGwdiXmZhBPfzZms1YR8CNHtpytxwROXtV
            4Y6FduI8AFiheUTDPm7qV1Ps74eDXLZS8ccjKiPO6u27cx6hUfQKlluEiV0HHaHs
            WxDUo1KM+TM2LgObxhxX+pwonTPAsc1Q+XZxB6ZH2WvphxAX4bMIjmcCgYEAy8Qs
            RQNSsc+cLzBOzg0JMPkXFA2wunyuq6g8+2qAAdBVEMTj/REUnrqqu1I9EAB7bC49
            LV8KaCwY0hh3HnCDBSBek1BztP0ub5myWvHk46p5B3Pl5tt/exk8DBThVeEXU8It
            nmRn06j+Izp9qm2pPTjwiI2JFxFFy9m48m5Dgs8CgYEAhpS6BZ6lcabb8VBzr7Ux
            k8kbv6SmH03Nn10rcjq7tZFKaFSVLf6XIGV+14plkytMwWS1OXS/1nMZ4VetlUyr
            tNNA9i3AjkRe1HGMebCl9EcKjUvzJ8lmkHk88WYY9b2Ib/D1KD3WeopYbFfod+nt
            ibUP/ylCqcGH4mxGYv4Ed7cCgYBWjnN7sWisTZN73Bptns37tqPeCd67qQENu/6D
            kf7kRloMMIbkViAbORITVmDH7lx/XLjyEAA8thapEGlxTbvqLpDeJPuxsybJk1FX
            Ho/rQdv9lXbgdPcXFGYUf74km+Qr2T1FnkMYfx3NWyhMgB62+fv7FZxZZyXt7QOK
            P9p/swKBgBiNcCT4wOfEKDP8Uy+yL64eXvwy+0b5uF2gDoaYN5JxPRZ80tjheyZ+
            Uxglei4Z6RzuWFVgmD4anBZDt8QV7L4y6ByLAxzkg5rxvlxTrOoRkeZIu8QcfrXH
            nZs9K88+xLUWj7ZAGvGrK5v/Xfm1ape8unwXTXPs7fNz72p3TfYm
            -----END RSA PRIVATE KEY-----
            PRIVATEKEY;


        // Tool and platform keychains both need both a private and public key,
        // as this provides info for both the tool and platform
        $toolKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $sharedPublicKey,
            $sharedPrivateKey,
        );
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
            'Schoolbox [vagrant] Platform',
            $this->baseUrl->toString(),
            (clone $this->baseUrl)->setPath('/lti-test/platform/oidc/authentication')->toString(),
        );

        // Configure a tool
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
        $tool = new Tool(
            'schoolboxVagrantTool',
            'Schoolbox [vagrant] Tool',
            $this->baseUrl->toString(),
            (clone $this->baseUrl)->setPath('/lti-test/tool/oidc/initiation')->toString(),
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
