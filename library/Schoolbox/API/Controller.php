<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Alaress\Web\Output;
use Authenticator;
use Firebase\JWT;
use Okta\JwtVerifier\Adaptors\FirebasePhpJwt;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\Exception\PasswordExpired as SchoolboxPasswordExpiredException;
use Schoolbox\Auth\JwtVerifierFactory;
use Schoolbox\Auth\TokenParser;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Zend\Uri\Uri;

class Controller extends Base
{
    /**
     * Default session length, in seconds: used as the default leeway for the
     * JWT verifier.
     */
    private const SESSION_LENGTH_DEFAULT = 1800;

    /**
     * @var Authenticator
     */
    private $auth;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var RegistryMapper
     */
    private $registryMapper;


    /**
     * @var ServiceMapper
     */
    private $serviceMapper;


    /**
     * Constructor
     *
     * @param Authenticator  $auth
     * @param UserMapper     $userMapper
     * @param RegistryMapper $registryMapper
     */
    public function __construct(
        Authenticator $auth,
        UserMapper $userMapper,
        RegistryMapper $registryMapper,
        ServiceMapper $serviceMapper
    ) {
        $this->auth           = $auth;
        $this->userMapper     = $userMapper;
        $this->registryMapper = $registryMapper;
        $this->serviceMapper  = $serviceMapper;
    }


    /**
     * Authenticates a user via either a username+password or JWT, and then
     * registers their app token.
     *
     * @noinspection PhpDocMissingThrowsInspection Spurious exception raised by setCode
     * @api          /api/register/{provider}
     * @since        145
     *
     * @param string $provider
     */
    public function register(string $provider): void
    {
        $providers = [Registry::PROVIDER_APPLE, Registry::PROVIDER_GOOGLE];

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
        $sessionUser = $this->authenticate($this->request->post->getArrayCopy());
        if ($sessionUser) {
            // token may be absent or blank, in which case we don't store anything in the registry
            $token = $this->request->post->get('token');
            if (!empty($token)) {
                $now = \Alaress_Date_Format::now();

                try {
                    $registry = $this->registryMapper->findById($sessionUser->getId(), $token, $provider);
                    $registry->setTouchedAt($now);
                } catch (MapperException $e) {
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

                        // De-register existing logins for other users when saving
                        $this->registryMapper->getDatabase()->transact(function () use ($token, $registry) {
                            $this->registryMapper->deleteByColumn('app_token', $token);
                            $this->registryMapper->save($registry);
                        });
                    } catch (MapperException $e) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $this->response->status->setCode(400);
                        $this->response->content->set($this->buildJsonOutput('Invalid app ID given'));
                        return;
                    }
                }
            }

            $this->response->content->set($this->buildAuthenticatedJsonOutput($sessionUser));
        }
    }


    /**
     * API endpoint for initializing a session based on a username/password or
     * a JWT, without registering an app.
     *
     * @api   /api/session
     * @since 190
     */
    public function initSession(): void
    {
        $sessionuser = $this->authenticate($this->request->post->getArrayCopy());
        if ($sessionuser) {
            if ($this->isJsonRequest()) {
                $this->response->content->set($this->buildAuthenticatedJsonOutput($sessionuser));
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
     * Authenticates the user based on data within the request.
     *
     * @noinspection PhpDocMissingThrowsInspection Spurious exception raised by setCode
     *
     * @param array $postData A copy of the POST data passed to the form
     * @return User|null The user who was authenticated, or null if they could
     *                   not be.
     */
    private function authenticate(array $postData): ?User
    {
        $response = null;
        $status   = null;
        try {
            if (isset($postData['username'], $postData['password'])) {
                return $this->authenticateViaPostData($postData['username'], $postData['password']);
            }
            if (isset($postData['jwt'])) {
                return $this->authenticateViaJwt($postData['jwt']);
            }

            $response = [
                'required' => 'Either the "username" and "password" or the "jwt" POST fields must be set'
            ];
            $status   = 400;
        } catch (JWT\BeforeValidException $e) {
            $response = 'JWT is not yet valid';
            $status   = 401;
        } catch (JWT\SignatureInvalidException $e) {
            $response = 'JWT signature is invalid';
            $status   = 401;
        } catch (JWT\ExpiredException $e) {
            $response = 'JWT expired';
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
        return $this->auth->authenticate($username, $password, true);
    }


    /**
     * Authenticates a user via a JWT passed in POST data.
     *
     * @param string $jwt
     * @return User The authenticated user (only if auth was successful)
     * @throws SchoolboxNotFoundException        if the user doesn't exist, or
     *                                           can't be found in the JWT
     * @throws SchoolboxAccountDisabledException if the account is disabled
     */
    private function authenticateViaJwt(string $jwt): User
    {
        $config = $this->getConfigLoader();

        // TODO DI this once it starts being used in multiple places
        $sessionLength = (int)$config->get('session_length');
        if ($sessionLength <= 0) {
            $sessionLength = self::SESSION_LENGTH_DEFAULT;
        }

        $adaptor            = new FirebasePhpJwt();
        $jwtVerifierFactory = new JwtVerifierFactory\Composite(
            new JwtVerifierFactory\Azure($adaptor),
            new JwtVerifierFactory\Cloudwork($adaptor),
        );

        $tokenParser = new TokenParser(
            $this->userMapper,
            $jwtVerifierFactory->build(
                $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER),
                $config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID),
                $sessionLength,
            ),
            explode(',', $config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTIFYING_CLAIMS)),
        );

        $user        = $tokenParser->findUserFromToken($jwt);
        $this->auth->createSession($user->getUsername(), true);
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
            'id'   => $user->getId(),
            'key'  => $user->getKey(),
            'role' => $user->getRole()->getType()
        ]);
    }
}
