<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Authenticator;
use Okta\JwtVerifier\Adaptors\FirebasePhpJwt;
use Okta\JwtVerifier\Discovery\Oidc;
use Okta\JwtVerifier\JwtVerifierBuilder;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Auth\TokenParser;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var Authenticator
     */
    private $auth;


    /**
     * @var RegistryMapper
     */
    private $registryMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


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
        RegistryMapper $registryMapper
    ) {
        $this->auth           = $auth;
        $this->userMapper     = $userMapper;
        $this->registryMapper = $registryMapper;
    }


    /**
     * @param string $provider
     */
    public function register($provider): void
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
        if ($this->authenticate()) {
            $sessionUser    = $this->getUser();
            $sessionUserId  = $sessionUser->getId();
            $sessionUserKey = $sessionUser->getKey();

            // token may be absent or blank, in which case we don't store anything in the registry
            $token = $this->request->post->get('token');
            if (!empty($token)) {
                $now = \Alaress_Date_Format::now();

                try {
                    $registry = $this->registryMapper->findById($sessionUserId, $token, $provider);
                    $registry->setTouchedAt($now);

                } catch (MapperException $e) {
                    try {
                        /** @var Registry $registry */
                        $registry = $this->registryMapper->createModel();
                        $registry
                            ->setUser($sessionUser)
                            ->setToken($token)
                            ->setService(ServiceMapper::getInstance()->findOneByColumnList([
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

                $this->registryMapper->save($registry);
            }

            $this->response->content->set(
                $this->buildJsonOutput([
                    'id'   => $sessionUserId,
                    'key'  => $sessionUserKey,
                    'role' => $sessionUser->getRole()->getType(),
                ])
            );
        }
    }


    /**
     * Authenticates the user based on data within the request.
     *
     * @noinspection PhpDocMissingThrowsInspection
     * @return bool Was a user auth session created?
     *              If it wasn't, JSON should have been put into the response.
     */
    private function authenticate(): bool
    {
        $data = $this->request->post->getArrayCopy();
        try {
            if (isset($data['username'], $data['password'])) {
                $this->authenticateViaPostData($data['username'], $data['password']);
            } elseif (isset($data['jwt'])) {
                $this->authenticateViaJwt($data['jwt']);
            } else {
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->response->status->setCode(400);
                $this->response->content->set(
                    $this->buildJsonOutput([
                        'required' => 'Either the "username" and "password" or the "jwt" POST fields must be set',
                    ])
                );
                return false;
            }
        } catch (SchoolboxAccessDeniedException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->response->status->setCode(401);
            $this->response->content->set($this->buildJsonOutput('Invalid username or password'));
            return false;
        } catch (SchoolboxAccountDisabledException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->response->status->setCode(400);
            $this->response->content->set($this->buildJsonOutput('Account disabled'));
            return false;
        } catch (SchoolboxNotFoundException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->response->status->setCode(400);
            $this->response->content->set($this->buildJsonOutput('User not found'));
            return false;
        }
        return true;
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
        $this->auth->authenticate($username, $password, true);
        return $this->getUser();
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
        $tokenParser = new TokenParser(
            $this->userMapper,
            (new JwtVerifierBuilder())
                ->setIssuer($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER))
                ->setAdaptor(new FirebasePhpJwt())
                ->setDiscovery(new Oidc())
                ->setAudience([$config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID)])
                ->build()
        );
        $user        = $tokenParser->findUserFromToken($jwt);
        $this->auth->createSession($user->getUsername(), true);
        return $this->getUser();
    }
}
