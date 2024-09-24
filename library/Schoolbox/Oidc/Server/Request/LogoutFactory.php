<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Request;

use Alaress_Date_Format;
use Http\Discovery\Psr17Factory;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Schoolbox\API\Token\Jwt;
use Schoolbox\API\Token\JwtParser\IParser;
use Schoolbox\Oidc\Server\RelyingParty\Model;
use Schoolbox\User;

/**
 * Class for generating back-channel logout requests to an oAuth client.
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html
 */
class LogoutFactory
{
    /**
     * Default expiry for logout token JWTs: 300 seconds.
     */
    private const LOGOUT_TOKEN_DEFAULT_EXPIRY = 300;

    /**
     * Base claim contents for the logout token: identifies the token as a
     * logout token.
     */
    private const LOGOUT_TOKEN_BASE_CLAIM = [
        'events' => ['http://schemas.openid.net/event/backchannel-logout' => []],
    ];

    /**
     * Constructor
     *
     * @param Jwt          $jwtBuilder   Required for building the logout token.
     * @param Psr17Factory $psr17Factory Required for building the request object and its body.
     * @param int          $expiry       Number of seconds in the future which logout tokens should expire:
     *                                   defaults to 300 seconds.
     */
    public function __construct(
        private Jwt $jwtBuilder,
        private Psr17Factory $psr17Factory,
        private int $expiry = self::LOGOUT_TOKEN_DEFAULT_EXPIRY,
    ) {
    }

    /**
     * Generate a HTTP request for logging `$user` out of their session with
     * `$client`, which may then be sent via a PSR-7 compatible HTTP client.
     *
     * @param User   $user   The user to generate a logout request for.
     * @param Model  $client The client for which to generate a logout request.
     * @param string $jwtId  The JWT ID for the logout token.
     * @return RequestInterface
     * @throws InvalidArgumentException if `$client` doesn't support logout
     */
    public function build(User $user, Model $client, string $jwtId): RequestInterface
    {
        if (!$client->getBackChannelLogoutUri()) {
            throw new InvalidArgumentException('Client does not have a back-channel logout URI.');
        }

        $logoutToken = $this->jwtBuilder->generate(
            $user,
            Alaress_Date_Format::now()->addSeconds($this->expiry),
            $jwtId,
            self::LOGOUT_TOKEN_BASE_CLAIM + [
                IParser::CLAIM_AUD => $client->getClientId(),
                IParser::CLAIM_IAT => Alaress_Date_Format::now()->getUnixTimeStamp(),
            ],
        );

        return $this->psr17Factory
            ->createRequest('POST', $client->getBackChannelLogoutUri())
            ->withBody($this->psr17Factory->createStream(http_build_query(['logout_token' => $logoutToken])));
    }
}
