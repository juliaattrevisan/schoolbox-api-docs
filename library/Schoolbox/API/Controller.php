<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Alaress\Web\Output;
use Alaress_Date_Format;
use Authenticator;
use Firebase\JWT;
use Schoolbox\API\Token\JwtParser;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\User;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\UserMapper;
use Laminas\Uri\Uri;

class Controller extends Base
{
    /**
     * Default session length, in seconds: used as the default leeway for the
     * JWT verifier.
     */
    private const SESSION_LENGTH_DEFAULT = 1800;

    /**
     * Authenticator, for creating a user session.
     */
    private Authenticator $auth;

    /**
     * User mapper, for finding the user to create a session for (once the
     * username has been extracted from a provided JWT).
     */
    private UserMapper $userMapper;

    /**
     * Registry mapper, for performing CRUD actions on a registration for a
     * mobile app.
     */
    private RegistryMapper $registryMapper;

    /**
     * Service mapper, for finding the mobile app to register the authenticating
     * user within.
     */
    private ServiceMapper $serviceMapper;

    /**
     * For validating JWT ID
     */
    private TokenMapper $tokenMapper;

    /**
     * JWT parser, for validating and identifying a user in a token generated
     * by any of:
     * * this instance
     * * this instance's oAuth provider
     * * another instance (as stored in the instance registry table)
     */
    private JwtParser $jwtParser;

    /**
     * Constructor
     *
     * @param Authenticator  $auth
     * @param UserMapper     $userMapper
     * @param RegistryMapper $registryMapper
     * @param ServiceMapper  $serviceMapper
     * @param TokenMapper    $tokenMapper
     * @param JwtParser      $jwtParser
     */
    public function __construct(
        Authenticator $auth,
        UserMapper $userMapper,
        RegistryMapper $registryMapper,
        ServiceMapper $serviceMapper,
        TokenMapper $tokenMapper,
        JwtParser $jwtParser
    ) {
        $this->auth           = $auth;
        $this->userMapper     = $userMapper;
        $this->registryMapper = $registryMapper;
        $this->serviceMapper  = $serviceMapper;
        $this->tokenMapper    = $tokenMapper;
        $this->jwtParser      = $jwtParser;
    }


    /**
     * Authenticates a user via either a username+password or JWT, and then
     * registers their app token.
     *
     * @noinspection PhpDocMissingThrowsInspection Spurious exception raised by setCode
     * @api          POST /api/register/{provider}
     * @since        145
     *
     * @param string $provider
     */
    public function register(string $provider): void
    {
        $providers = [
            Registry::PROVIDER_APPLE,
            Registry::PROVIDER_GOOGLE,
            Registry::PROVIDER_DIGISTORM,
        ];

        // check the provider is valid
        if (!in_array($provider, $providers, true)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->response->status->setCode(400);
            $this->response->content->set(
                $this->buildJsonOutput(
                    "Invalid provider: '$provider'. Accepted providers are: " . implode(', ', $providers)
                )
            );
            return;
        }

        // Authenticate, and if so register app
        // (For now, don't allow a query string JWT when registering)
        $sessionUser = $this->authenticate(false);
        if ($sessionUser) {
            // For Digistorm: token is always the user's username
            // Otherwise: token may be absent or blank, in which case we don't
            // store anything in the registry
            $token = $provider === Registry::PROVIDER_DIGISTORM
                ? $sessionUser->getUsername()
                : $this->request->post->get('token');
            if (!empty($token)) {
                $now = Alaress_Date_Format::now();

                $deleteExistingToken = false;
                try {
                    $registry = $this->registryMapper->findByTokenAndProviderForUser(
                        $sessionUser->getId(),
                        $token,
                        $provider
                    );
                    $registry->setTouchedAt($now);
                } catch (MapperException $e) {
                    $deleteExistingToken = true;
                    try {
                        /** @var Registry $registry */
                        $registry = $this->registryMapper->createModel();
                        $registry
                            ->setUser($sessionUser)
                            ->setToken($token)
                            ->setService($this->serviceMapper->findOneByColumnList([
                                'app_identifier' => $this->request->post->get('appId'),
                                'provider'       => $provider,
                            ]))
                            ->setCreatedAt($now)
                            ->setTouchedAt($now);

                        // if a device ID is given, store that too. The mapper will auto-dedupe because of unique constraints
                        $deviceId = $this->request->post->get('deviceId');
                        if (isset($deviceId)) {
                            $registry->setDeviceId($deviceId);
                        }
                    } catch (MapperException $e) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $this->response->status->setCode(400);
                        $this->response->content->set($this->buildJsonOutput('Invalid app ID given'));
                        return;
                    }
                }

                // If this is a new registration: delete registrations for the
                // token when saving.
                // Also, if this is the Digistorm provider:
                // de-register all other registrations for this user
                // (as the Digistorm provider is superseding the others)
                $this->registryMapper->getDatabase()->transact(function () use ($token, $registry, $sessionUser, $deleteExistingToken) {
                    if ($deleteExistingToken) {
                        $this->registryMapper->deleteByColumn('app_token', $token);
                    }
                    if ($registry->getService()->getProviderName() === Registry::PROVIDER_DIGISTORM) {
                        $this->registryMapper->deregisterForProvider($sessionUser, Registry::PROVIDER_GOOGLE);
                        $this->registryMapper->deregisterForProvider($sessionUser, Registry::PROVIDER_APPLE);
                    }
                    $this->registryMapper->save($registry);
                });
            }

            $this->response->content->set($this->buildAuthenticatedJsonOutput($sessionUser));
        }
    }


    /**
     * API endpoint for initializing a session based on a username/password or
     * a JWT, without registering an app.
     *
     * @api   POST /api/session
     * @since 190
     */
    public function initSession(): void
    {
        $sessionUser = $this->authenticate(true);
        if ($sessionUser) {
            if ($this->isJsonRequest()) {
                $this->response->content->set($this->buildAuthenticatedJsonOutput($sessionUser));
            } else {
                // Redirect somewhere (locally only?)
                $page = $this->getRequest()->query->get('redirect');
                $pageUri = new Uri($page);

                //Determine if page redirect is valid
                if ($page && $pageUri->isValidRelative()) {
                    $this->response->redirect->to($page);
                } else {
                    $this->response->redirect->to('/');
                }
            }
        }
    }


    /**
     * API endpoint for initializing a session based on a JWT in a query string.
     *
     * @api   GET /api/session
     * @since 21.0
     */
    public function initSessionFromQueryString(): void
    {
        $this->initSession();
    }


    /**
     * Authenticates the user based on data within the request.
     *
     * A user may be authenticated by one of:
     * * a username and password in POST
     * * a JWT in POST
     * * a JWT in the query string (if `$allowQueryStringJwt` is true)
     *
     * @noinspection PhpDocMissingThrowsInspection Spurious exception raised by setCode
     *
     * @param bool $allowQueryStringJwt
     * @return User|null The user who was authenticated, or null if they could
     *                   not be.
     */
    private function authenticate(bool $allowQueryStringJwt): ?User
    {
        $postData = $this->request->post->getArrayCopy();
        try {
            if (isset($postData['username'], $postData['password'])) {
                return $this->authenticateViaPostData($postData['username'], $postData['password']);
            }
            if (isset($postData['jwt'])) {
                return $this->authenticateViaJwt($postData['jwt']);
            }
            if ($allowQueryStringJwt) {
                $queryStringJwt = $this->request->query->get('jwt');
                if (isset($queryStringJwt)) {
                    return $this->authenticateViaJwt($queryStringJwt);
                }
            }

            $response = [
                'required' => $allowQueryStringJwt
                    ? 'Either the "username" and "password" POST fields, the "jwt" POST field or the "jwt" query string field must be set'
                    : 'Either the "username" and "password" or the "jwt" POST fields must be set'
            ];
            $status   = 400;
        } catch (JWT\BeforeValidException $e) {
            $response = 'JWT is not yet valid';
            $status   = 401;
        } catch (JWT\SignatureInvalidException $e) {
            $response = 'JWT signature is invalid';
            $status   = 401;
        } catch (JWT\ExpiredException $e) {
            $response = empty($e->getMessage()) ? 'JWT expired' : $e->getMessage();
            $status   = 401;
        } catch (SchoolboxAccessDeniedException $e) {
            $response = 'Invalid username or password';
            $status   = 401;
        } catch (SchoolboxAccountDisabledException $e) {
            $response = 'Account disabled';
            $status   = 400;
        } catch (SchoolboxNotFoundException $e) {
            $response = 'User not found';
            $status   = 400;
        } catch (SchoolboxPasswordExpiredException $e) {
            $response = 'Password has expired';
            $status   = 400;
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->response->status->setCode($status);
        $this->response->content->set($this->buildJsonOutput($response));
        return null;
    }


    /**
     * Authenticates a user given the username/password in POST data.
     *
     * @param string $username
     * @param string $password
     * @return User The authenticated user (only if auth was successful)
     * @throws SchoolboxNotFoundException        if the user doesn't exist
     * @throws SchoolboxAccessDeniedException    if the password is incorrect
     * @throws SchoolboxAccountDisabledException if the account is disabled
     */
    private function authenticateViaPostData(string $username, string $password): User
    {
        // TODO this used to remember the authenticated user: should it?
        return $this->auth->authenticate($username, $password);
    }


    /**
     * Authenticates a user via a JWT passed in POST data.
     *
     * @param string $jwt
     * @return User The authenticated user (only if auth was successful)
     */
    private function authenticateViaJwt(string $jwt): User
    {
        $user = $this->jwtParser->determineUserFromJwt($jwt);
        $this->auth->createSession($user->getUsername());
        return $user;

//        $claims = null;
//        try {
//            $claims = $this->tokenValidator->decode($jwt);
//        } catch (\Throwable $e) {
//            // noop: try the oAuth provider's JWT instead
//        }
//
//        if (isset($claims)) {
//            $user   = $this->userMapper->findByUsername($claims[MessagePayloadInterface::CLAIM_SUB]);
//            $token  = null;
//            if ($user && isset($claims[MessagePayloadInterface::CLAIM_JTI]) && $this->tokenValidator->issuedBySchoolbox($claims)) {
//                // if issued by schoolbox and jti is provided, validate the jti
//                try {
//                    /** @var User\Token\Model $token */
//                    $token = $this->tokenMapper->findOneByColumnList(
//                        [
//                            'user_id' => $user->getId(),
//                            'jwt_id'  => $claims[MessagePayloadInterface::CLAIM_JTI],
//                        ]
//                    );
//                    if ($token->getExpiry() !== null && !$token->getExpiry()->isNull() && $token->getExpiry()->isPast()) {
//                        throw new JWT\ExpiredException(
//                            $token->getType() === User\Token\Model::TYPE_QRCODE ? 'This login token is no longer valid. Please ask for a new token to be created.' : ''
//                        );
//                    }
//                } catch (MapperException $e) {
//                    // could not find a token with user id and jwt id
//                    throw new JWT\SignatureInvalidException($e);
//                }
//            }
//            if ($user) {
//                // TODO this used to remember the authenticated user: should it?
//                $this->auth->createSession($user->getUsername(), $token);
//                return $user;
//            }
//            throw new SchoolboxNotFoundException();
//        }
//
//        // TODO DI this once it starts being used in multiple places
//        $sessionLength = (int)$config->get('session_length');
//        if ($sessionLength <= 0) {
//            $sessionLength = self::SESSION_LENGTH_DEFAULT;
//        }
//
//        $jwtVerifierFactory = new JwtVerifierFactory\Configured(
//            $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER_TYPE),
//            new FirebasePhpJwt(null, $sessionLength)
//        );
//
//        $tokenParser = new TokenParser(
//            $this->userMapper,
//            $jwtVerifierFactory->build(
//                $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER),
//                $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID),
//                $sessionLength,
//            ),
//            explode(',', $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS)),
//        );
//
//        $user = $tokenParser->findUserFromToken($jwt);
//        // TODO this used to remember the authenticated user: should it?
//        $this->auth->createSession($user->getUsername());
//        return $user;
    }


    /**
     * Build JSON output for an authenticated user.
     *
     * @param User $user The user to build output for.
     *                   In practise this will always be the logged-in user.
     * @return Output\Json
     */
    private function buildAuthenticatedJsonOutput(User $user): Output\Json
    {
        return $this->buildJsonOutput([
            'id'       => $user->getId(),
            'key'      => $user->getKey(),
            'role'     => $user->getRole()->getType(),
            'username' => $user->getUsername(),
        ]);
    }
}
