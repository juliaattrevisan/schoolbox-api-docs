<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Authenticator;
use Schoolbox\API\Token\JwtParser;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\NotAuthenticated;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\User;
use Schoolbox\Url\Uri;
use SensitiveParameter;

class Controller extends Base
{
    /**
     * Authenticator, for creating a user session.
     */
    private Authenticator $auth;

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
     * JWT parser, for validating and identifying a user in a token generated
     * by any of:
     * * this instance
     * * this instance's oAuth provider
     * * another instance (as stored in the instance registry table)
     */
    private JwtParser\IParser $jwtParser;

    /**
     * Constructor
     *
     * @param Authenticator     $auth
     * @param RegistryMapper    $registryMapper
     * @param ServiceMapper     $serviceMapper
     * @param JwtParser\IParser $jwtParser
     */
    public function __construct(
        Authenticator $auth,
        RegistryMapper $registryMapper,
        ServiceMapper $serviceMapper,
        JwtParser\IParser $jwtParser,
    ) {
        $this->auth           = $auth;
        $this->registryMapper = $registryMapper;
        $this->serviceMapper  = $serviceMapper;
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
            Registry::PROVIDER_FIREBASE,
        ];

        // check the provider is valid
        if (!in_array($provider, $providers, true)) {
            $this->response = $this->responseAsJson(
                "Invalid provider: '$provider'. Accepted providers are: " . implode(', ', $providers)
            )->withStatus(400);
            return;
        }

        // Authenticate, and if so register app
        // (For now, don't allow a query string JWT when registering)
        // If Firebase: just use whoever's logged in at this time
        $sessionUser = $provider === Registry::PROVIDER_FIREBASE
            ? $this->getUser()
            : $this->authenticate(false);
        if ($sessionUser) {
            // For Digistorm: token is always the user's username
            // Otherwise: token may be absent or blank, in which case we don't
            // store anything in the registry
            $token = $provider === Registry::PROVIDER_DIGISTORM
                ? $sessionUser->getUsername()
                : $this->requestPost->get('token');
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
                                'app_identifier' => $this->requestPost->get('appId'),
                                'provider'       => $provider,
                            ]))
                            ->setCreatedAt($now)
                            ->setTouchedAt($now);

                        // if a device ID is given, store that too. The mapper will auto-dedupe because of unique constraints
                        $deviceId = $this->requestPost->get('deviceId');
                        if (isset($deviceId)) {
                            $registry->setDeviceId($deviceId);
                        }
                    } catch (MapperException $e) {
                        $this->response = $this->responseAsJson('Invalid app ID given')->withStatus(400);
                        return;
                    }
                }

                // If this is a new registration: delete registrations for the
                // token when saving.
                // Also, if this is the Digistorm provider:
                // de-register all other registrations for this user
                // (as the Digistorm provider is superseding the others)
                $this->registryMapper->getDatabase()->transact(function () use ($token, $registry, $sessionUser, $deleteExistingToken): void {
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

            $this->assignJsonResponseForAuthenticatedUser($sessionUser, 201);
        }
    }

    /**
     * Authenticates a user via either a username+password or JWT, and then
     * removes their app token.
     *
     * @noinspection PhpDocMissingThrowsInspection Spurious exception raised by setCode
     * @api          DELETE /api/register/{provider}
     * @since        24.0
     *
     * @param string $provider
     */
    public function delete(string $provider): void
    {
        $providers = [
            Registry::PROVIDER_APPLE,
            Registry::PROVIDER_GOOGLE,
            Registry::PROVIDER_DIGISTORM,
            Registry::PROVIDER_FIREBASE,
        ];

        // check the provider is valid
        if (!in_array($provider, $providers, true)) {
            $this->response = $this->responseAsJson(
                "Invalid provider: '$provider'. Accepted providers are: " . implode(', ', $providers)
            )->withStatus(400);
            return;
        }

        // Authenticate, and if so register app
        // (For now, don't allow a query string JWT when registering)
        // If Firebase: just use whoever's logged in at this time
        $sessionUser = $provider === Registry::PROVIDER_FIREBASE
            ? $this->getUser()
            : $this->authenticate(false);
        if ($sessionUser) {
            // For Digistorm: token is always the user's username
            // Otherwise: token may be absent or blank, in which case we don't
            // store anything in the registry
            $token = $provider === Registry::PROVIDER_DIGISTORM
                ? $sessionUser->getUsername()
                : $this->request->getParsedBody()["token"];
            if (!empty($token)) {
                try {
                    $registry = $this->registryMapper->findByTokenAndProviderForUser(
                        $sessionUser->getId(),
                        $token,
                        $provider
                    );
                    $this->registryMapper->delete($registry);
                    $this->response = $this->responseAsJson('Successfully deleted registration')->withStatus(201);
                    return;
                } catch (MapperException $e) {
                    $this->response = $this->responseAsJson('Unable to find app registration')->withStatus(400);
                    return;
                }
            }
        }
        $this->response = $this->responseAsJson('We should have done something before this...')->withStatus(400);
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
                $this->assignJsonResponseForAuthenticatedUser($sessionUser);
            } else {
                $this->assignRedirectResponseForAuthenticatedUser((string)$this->requestQuery->get('redirect'));
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
        $postData = $this->requestPost->getArrayCopy();
        try {
            if (isset($postData['username'], $postData['password'])) {
                return $this->authenticateViaPostData($postData['username'], $postData['password']);
            }
            if (isset($postData['jwt'])) {
                return $this->authenticateViaJwt($postData['jwt']);
            }
            if ($allowQueryStringJwt) {
                $queryStringJwt = $this->requestQuery->get('jwt') ?? $this->requestQuery->get('id_token');
                if ($queryStringJwt) {
                    return $this->authenticateViaJwt($queryStringJwt);
                }
            }

            $response = [
                'required' => $allowQueryStringJwt
                    ? 'Either the "username" and "password" POST fields, the "jwt" POST field or the "jwt" query string field must be set'
                    : 'Either the "username" and "password" or the "jwt" POST fields must be set'
            ];
            $status   = 400;
        } catch (JwtParser\Exception\Unparseable $e) {
            $response = 'JWT is not valid: ' . $e->getMessage();
            $status   = 401;
        } catch (JwtParser\Exception\Rejected $e) {
            $response = 'JWT rejected: ' . $e->getMessage();
            $status   = 401;
        } catch (JwtParser\Exception\InvalidIdentity $e) {
            $response = 'JWT identifies invalid user: ' . $e->getMessage();
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

        $this->response = $this->responseAsJson($response)->withStatus($status);
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
    private function authenticateViaPostData(string $username, #[SensitiveParameter] string $password): User
    {
        return $this->auth->authenticate($username, $password);
    }


    /**
     * Authenticates a user via a JWT passed in POST data.
     *
     * @param string $jwt
     * @return User The authenticated user (only if auth was successful)
     * @throws JwtParser\Exception\Base if JWT was rejected for whatever reason
     * @throws MapperException
     * @throws NotAuthenticated
     */
    private function authenticateViaJwt(#[SensitiveParameter] string $jwt): User
    {
        $user = $this->jwtParser->determineUser($jwt);
        $this->auth->createSession($user->getUsername(), null, $this->jwtParser->bypassIpTracking());
        return $user;
    }

    /**
     * Assigns JSON response (headers and output) for an authenticated user.
     *
     * @param User     $user   The user to build output for: in practise this
     *                         will always be the logged-in user.
     * @param int|null $status A status code.
     */
    private function assignJsonResponseForAuthenticatedUser(User $user, ?int $status = null): void
    {
        $this->response = $this->responseAsJson([
            'id'       => $user->getId(),
            'key'      => $user->getKey(),
            'role'     => $user->getRole()->getType(),
            'username' => $user->getUsername(),
        ]);
        if (isset($status)) {
            $this->response = $this->response->withStatus($status);
        }
    }

    /**
     * Assigns redirect response (headers and output) for an authenticated user.
     *
     * @param string $page The (relative) path to redirect to.
     */
    private function assignRedirectResponseForAuthenticatedUser(string $page): void
    {
        // Redirect somewhere (locally only)
        if ($page && (new Uri($page))->isAbsolutePath()) {
            $this->response = $this->redirectFound($page);
        } else {
            $this->response = $this->redirectFound('/');
        }
    }
}
