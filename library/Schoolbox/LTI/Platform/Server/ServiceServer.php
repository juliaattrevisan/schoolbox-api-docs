<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 *
 * @see \OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer
 *      for base implementation
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Server;

use Http\Message\ResponseFactory;
use Nyholm\Psr7\Factory\HttplugFactory;
use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\RequestAccessTokenValidatorInterface;
use OAT\Library\Lti1p3Core\Service\Server\Handler\LtiServiceServerRequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Modified implementation of `\OAT\Library\Lti1p3Core\Service\Server\LtiServiceServer`:
 * its `handle` method is updated to no longer attempt to catch exceptions.
 */
class ServiceServer implements RequestHandlerInterface
{
    /**
     * Constructor
     *
     * @param RequestAccessTokenValidatorInterface    $validator
     * @param LtiServiceServerRequestHandlerInterface $handler
     * @param ResponseFactory|null                    $factory
     * @param LoggerInterface|null                    $logger
     */
    public function __construct(
        private RequestAccessTokenValidatorInterface $validator,
        private LtiServiceServerRequestHandlerInterface $handler,
        private ?ResponseFactory $factory = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->factory = $factory ?? new HttplugFactory();
        $this->logger  = $logger ?? new NullLogger();
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Throwable Anything `$this->handler->handleValidatedServiceRequest` may throw
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $allowedMethods = array_map('strtolower', $this->handler->getAllowedMethods());

        if (!empty($allowedMethods) && !in_array(strtolower($request->getMethod()), $allowedMethods)) {
            $message = sprintf('Not acceptable request method, accepts: [%s]', implode(', ', $allowedMethods));
            $this->logger->error($message);
            return $this->factory->createResponse(405, null, [], $message);
        }

        $allowedContentType = $this->handler->getAllowedContentType();
        $contentTypeHeader  = 'get' === strtolower($request->getMethod()) ? 'Accept' : 'Content-Type';

        if (
            !empty($allowedContentType)
            && !str_contains($request->getHeaderLine($contentTypeHeader), $allowedContentType)
        ) {
            $message = sprintf('Not acceptable request content type, accepts: %s', $allowedContentType);
            $this->logger->error($message);
            return $this->factory->createResponse(406, null, [], $message);
        }

        $validationResult = $this->validator->validate($request, $this->handler->getAllowedScopes());
        if ($validationResult->hasError()) {
            $this->logger->error($validationResult->getError());
            return $this->factory->createResponse(401, null, [], $validationResult->getError());
        }

        $response = $this->handler->handleValidatedServiceRequest($validationResult, $request);
        $this->logger->info(sprintf('%s service success', $this->handler->getServiceName()));
        return $response;
    }
}
