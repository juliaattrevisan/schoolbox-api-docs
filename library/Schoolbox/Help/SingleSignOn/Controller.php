<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Help\SingleSignOn;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Authenticator;
use DomainException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\NotAuthenticated;
use Schoolbox\Help\InstanceRegistry\Mapper as InstanceRegistryMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Laminas\Uri\Uri;

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
     * The name of the JWT query string parameter: this contains the JWT passed
     * to the Help instance.
     */
    private const QUERY_STRING_JWT = 'jwt';

    /**
     * The name of the page query string parameter: this contains a path to
     * redirect to on Help once it has received the JWT.
     */
    private const QUERY_STRING_PAGE = 'returnUrl';

    /**
     * By default: redirect to the dashboard.
     */
    private const QUERY_STRING_PAGE_DEFAULT = '/';

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
            Alaress_Date_Format::now()->addSeconds(self::JWT_EXPIRY),
            null,
            [
                self::CLAIM_EMAIL       => $this->getUser()->getEmail(),
                self::CLAIM_INSTANCE_ID => INSTANCE_ID,
            ]
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $url = (new Uri($this->getConfigLoader()->get('schoolbox_help_url')))
            ->setPath($this->router->generate('helpSso.receiveTokenFromInstance'))
            ->setQuery([
                self::QUERY_STRING_JWT  => $jwt,
                self::QUERY_STRING_PAGE => $this->determinePageQueryStringValue(),
            ]);

        $this->response->redirect->seeOther($url->toString());
    }

    /**
     * Receive a JWT (in the "jwt" query string parameter), and use it to
     * identify and authenticate a user.
     *
     * @deprecated URLs which point here should instead point to
     *             `\Schoolbox\API\Controller::initSessionFromQueryString`:
     *             its JWT parser may now parse JWTs from other instances.
     *
     * @api GET /help/sso/receive
     * @since 21.0
     */
    public function receiveTokenFromInstance(): void
    {
        $page = $this->determinePageQueryStringValue();

        // Hold on, are you already logged in?
        // Just go straight to the page without authenticating.
        if ($this->getUser()->getId()) {
            $this->response->redirect->seeOther($page);
            return;
        }

        $this->response->redirect->permanentRedirect(
            (new Uri())
                ->setPath($this->router->generate('api.initSessionFromQueryString'))
                ->setQuery([
                    'jwt'      => $this->request->query->get(self::QUERY_STRING_JWT),
                    'redirect' => $page,
                ])
        );

//        try {
//            $this->authenticateUserFromToken();
//            $this->response->redirect->seeOther($page);
//        } catch (
//            UnexpectedValueException
//            | DomainException
//            | MapperException
//            | SignatureInvalidException
//            | BeforeValidException
//            | ExpiredException
//            | NotAuthenticated $e
//        ) {
//            // For now: just redirect to login page
//            // Later: show a more useful message
//            $loginUrl = (new Uri($this->router->generate('login.getLogin')))
//                ->setQuery(['page' => $page]);
//            $this->response->redirect->seeOther($loginUrl->toString());
//        }
    }


//    /**
//     * @throws UnexpectedValueException  if no "jwt" query string param exists
//     * @throws UnexpectedValueException  if JWT is incorrectly formed
//     * @throws DomainException           if JWT payload is invalid JSON
//     * @throws UnexpectedValueException  if JWT contains no instance ID claim
//     * @throws MapperException           if instance ID matches no instance
//     * @throws SignatureInvalidException if JWT signature invalid
//     * @throws BeforeValidException      if JWT is not yet valid or issued
//     * @throws ExpiredException          if JWT has since expired
//     * @throws NotAuthenticated          if user identified in JWT cannot be
//     *                                   authenticated (because they don't exist
//     *                                   or their account is inactive)
//     */
//    private function authenticateUserFromToken(): void
//    {
//        // Find the JWT
//        $jwt = $this->request->query->get(self::QUERY_STRING_JWT);
//        if (!isset($jwt)) {
//            throw new UnexpectedValueException(
//                sprintf('"%s" query string parameter must be provided', self::QUERY_STRING_JWT)
//            );
//        }
//
//        // Decode the JWT _without_ verifying it,
//        // in order to determine the instance whence it came
//        $payload = $this->tokenBuilder->decodeWithoutVerification($jwt);
//
//        // Now we know the instance:
//        // Look it up in the registry table, and get the public key stored there
//        // so that the JWT can be verified
//        $instanceId = $payload[self::CLAIM_INSTANCE_ID] ?? null;
//        if (!isset($instanceId)) {
//            throw new UnexpectedValueException(sprintf('JWT must contain an "%s" claim', self::CLAIM_INSTANCE_ID));
//        }
//        $instanceDetails = $this->instanceRegistryMapper->findByInstanceId((string)$instanceId);
//
//        // Verify the payload
//        $verifiedPayload = $this->tokenBuilder->decode($jwt, $instanceDetails->getJwtPublicKey());
//
//        // Now the JWT is decoded and verified:
//        // create a session for the user matching the email address in the JWT
//        $email = $verifiedPayload[self::CLAIM_EMAIL] ?? null;
//        if (!isset($email)) {
//            throw new UnexpectedValueException(sprintf('JWT must contain an "%s" claim', self::CLAIM_EMAIL));
//        }
//
//        // The email must be valid too, or at least not blank
//        $email = (string)$email;
//        if (trim($email) === '') {
//            throw new UnexpectedValueException(sprintf('JWT "%s" claim must not be empty', self::CLAIM_EMAIL));
//        }
//        $this->auth->createSession($email);
//    }

    /**
     * Determine what URL should be redirected to after the JWT is received.
     *
     * @return string
     */
    private function determinePageQueryStringValue(): string
    {
        $url = new Uri(
            $this->request->query->get(
                self::QUERY_STRING_PAGE,
                self::QUERY_STRING_PAGE_DEFAULT
            )
        );

        // Don't allow non-relative redirects
        return $url->isValidRelative()
            ? $url->toString()
            : self::QUERY_STRING_PAGE_DEFAULT;
    }
}
