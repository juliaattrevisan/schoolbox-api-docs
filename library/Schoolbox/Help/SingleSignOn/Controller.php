<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Web\Controller\Base;
use Authenticator;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\NotAuthenticated as SchoolboxNotAuthenticatedException;
use Schoolbox\InstanceRegistry\Mapper as InstanceRegistryMapper;
use Schoolbox\UserMapper;
use Zend\Uri\Uri;

/**
 * Controller for SSO endpoints
 */
class Controller extends Base
{
    /**
     * Expire the JWT after 5 minutes.
     */
    private const JWT_EXPIRY = 300;

    /**
     * Key for the email JWT claim. This is a public claim, so it doesn't need
     * a prefix.
     */
    private const CLAIM_EMAIL = 'email';

    /**
     * Key for the instance ID custom JWT claim.
     */
    private const CLAIM_INSTANCE_ID = Jwt::CUSTOM_CLAIM_NAMESPACE . 'instance_id';

    /**
     * Token builder: used to construct and parse tokens.
     */
    private Jwt $tokenBuilder;

    /**
     * User mapper
     */
    private UserMapper $userMapper;

    /**
     * Authentication class
     */
    private Authenticator $auth;

    /**
     * Instance registry mapper
     */
    private InstanceRegistryMapper $instanceRegistryMapper;

    /**
     * Constructor
     *
     * @param Jwt                    $tokenBuilder
     * @param Authenticator          $auth
     * @param UserMapper             $userMapper
     * @param InstanceRegistryMapper $instanceRegistryMapper
     */
    public function __construct(
        Jwt $tokenBuilder,
        Authenticator $auth,
        UserMapper $userMapper,
        InstanceRegistryMapper $instanceRegistryMapper
    ) {
        $this->tokenBuilder           = $tokenBuilder;
        $this->auth                   = $auth;
        $this->userMapper             = $userMapper;
        $this->instanceRegistryMapper = $instanceRegistryMapper;
    }

    /**
     * Generate a JWT, and send it to Schoolbox Help for single sign-on.
     *
     * This will redirect to `receiveTokenFromInstance` on the Help instance,
     * with the JWT in the "jwt" query string parameter.
     *
     * @api GET /help/sso/send
     * @since 21.0
     */
    public function sendTokenToHelp(): void
    {
        // Generate a JWT
        $jwt = $this->tokenBuilder->generate(
            $this->getUser(),
            self::JWT_EXPIRY,
            [
                self::CLAIM_EMAIL       => $this->getUser()->getEmail(),
                self::CLAIM_INSTANCE_ID => INSTANCE_ID,
            ]
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $url = (new Uri($this->getConfigLoader()->get('schoolbox_help_url')))
            ->setPath($this->router->generate('helpSso.receiveTokenFromInstance'))
            ->setQuery(['jwt' => $jwt]);

        $this->response->redirect->seeOther($url->toString());
    }

    /**
     * Receive a JWT (in the "jwt" query string parameter), and use it to
     * identify and authenticate a user.
     *
     * @api GET /help/sso/receive
     * @since 21.0
     */
    public function receiveTokenFromInstance(): void
    {
        // Decode the JWT _without_ verifying it,
        // in order to determine the instance whence it came
        $jwt     = $this->request->query->get('jwt');
        $payload = $this->tokenBuilder->decodeWithoutVerification($jwt);

        // Now we know the instance:
        // Look it up in the registry table, and get the public key stored there
        // so that the JWT can be verified
        $instanceDetails = $this->instanceRegistryMapper->findByInstanceId(
            $payload[self::CLAIM_INSTANCE_ID],
        );

        $verifiedPayload = $this->tokenBuilder->decode($jwt, $instanceDetails->getJwtPublicKey());

        // Now the JWT is decoded and verified:
        // create a session for the user matching the email address in the JWT
        try {
            $this->auth->createSession($verifiedPayload[self::CLAIM_EMAIL]);
            // For now, redir to dashboard
            $this->response->redirect->seeOther('/');
        } catch (SchoolboxNotAuthenticatedException $e) {
            // Something went wrong: redir to login
            $this->response->status->setCode(403);
        }
    }
}
