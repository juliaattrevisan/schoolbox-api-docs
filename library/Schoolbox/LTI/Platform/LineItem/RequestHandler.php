<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\LineItem;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Repository\AccessTokenRepository;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidator;
use OAT\Library\Lti1p3Core\Service\Server\Handler\LtiServiceServerRequestHandlerInterface;
use OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Common method for handling requests to an LTI service.
 */
trait RequestHandler
{
    /**
     * Create a response for this controller's request.
     *
     * @param ServerRequestInterface                  $request          The incoming HTTP request.
     * @param LtiServiceServerRequestHandlerInterface $handler          The handler for the incoming request:
     *                                                                  does something with an LTI object.
     * @param RegistrationRepositoryInterface         $registrations    Repository of LTI tool+platform registrations:
     *                                                                  used for determining if the requester is valid.
     * @param CacheItemPoolInterface                  $accessTokenCache Cache for OIDC access tokens: used to persist
     *                                                                  these tokens, and to verify if they are still
     *                                                                  valid.
     * @param string                                  $publicKey        The public key used to decode the request's JWT.
     * @param LoggerInterface                         $logger           Logger: the LTI service may write log messages.
     * @return ResponseInterface
     * @throws OAuthServerException if the request is invalid.
     */
    protected function handleRequest(
        ServerRequestInterface $request,
        LtiServiceServerRequestHandlerInterface $handler,
        RegistrationRepositoryInterface $registrations,
        CacheItemPoolInterface $accessTokenCache,
        string $publicKey,
        LoggerInterface $logger,
    ): ResponseInterface {
        // Validate access token
        $validator      = new RequestAccessTokenValidator($registrations, $logger);
        $resourceServer = new ResourceServer(new AccessTokenRepository($accessTokenCache, $logger), $publicKey);

        // Handle the request
        return new LtiServiceServer($validator, $handler, $logger)
            ->handle($resourceServer->validateAuthenticatedRequest($request));
    }
}
