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
use Laminas\Uri\Uri;

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
        JwtParser\IParser $jwtParser
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
     * @throws JwtParser\Exception\Base if JWT was rejected for whatever reason
     * @throws MapperException
     * @throws NotAuthenticated
     */
    private function authenticateViaJwt(string $jwt): User
    {
        $user = $this->jwtParser->determineUser($jwt);
        $this->auth->createSession($user->getUsername());
        return $user;
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
