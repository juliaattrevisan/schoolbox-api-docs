<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\API;

use Alaress\Web\Controller\Base;
use Authenticator;
use Okta\JwtVerifier\Discovery\Oidc;
use Okta\JwtVerifier\JwtVerifierBuilder;
use Schoolbox\Application\Exception\AccessDenied as SchoolboxAccessDeniedException;
use Schoolbox\Application\Exception\AccountDisabled as SchoolboxAccountDisabledException;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Auth\Adapter\FirebasePhpJwt;
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

    /** @var Authenticator */
    protected $auth;

    /** @var RegistryMapper */
    protected $registryMapper;


    /**
     * @param Authenticator $auth
     * @return self
     */
    public function setAuth(Authenticator $auth)
    {
        $this->auth = $auth;
        return $this;
    }


    /**
     * @param RegistryMapper $registryMapper
     * @return self
     */
    public function setRegistryMapper($registryMapper)
    {
        $this->registryMapper = $registryMapper;
        return $this;
    }


    /**
     * @param string $provider
     */
    public function register($provider)
    {
        $data      = (array)$this->getRequest()->post;
        $response  = $this->getResponse()->content;
        $providers = [Registry::PROVIDER_APPLE, Registry::PROVIDER_GOOGLE];

        // check the provider is valid
        if (!in_array($provider, $providers)) {
            $this->getResponse()->status->setCode(400);
            $response->set([
                'format' => 'json',
                'data'   => "Invalid provider: '$provider'. Accepted providers are: " . implode(', ', $providers),
            ]);
            return;
        }

        // Maybe someone's already authenticated?
        // (This might have happened as a result of token authentication)
        $sessionUser = $this->getUser();
        if (!($sessionUser && $sessionUser->getId())) {
            $sessionUser = $this->authenticateViaPostData($data);
            if (!$sessionUser) {
                // Authentication failed: return early
                return;
            }
        }

        // only obtain these *after* you've run authentication
        $sessionUserId  = $sessionUser->getId();
        $sessionUserKey = $sessionUser->getKey();

        // token may be absent or blank, in which case we don't store anything in the registry
        if (!empty($data['token'])) {
            $now = \Alaress_Date_Format::now();

            try {
                $registry = $this->registryMapper->findById($sessionUserId, $data['token'], $provider);
                $registry->setTouchedAt($now);

            } catch (MapperException $e) {
                try {
                    /** @var Registry $registry */
                    $registry = $this->registryMapper->createModel();
                    $registry
                        ->setUser($sessionUser)
                        ->setToken($data['token'])
                        ->setService(ServiceMapper::getInstance()->findOneByColumnList([
                            'app_identifier' => $data['appId'],
                            'provider'       => $provider,
                        ]))
                        ->setCreatedAt($now)
                        ->setTouchedAt($now);

                    // if a device ID is given, store that too. The mapper will auto-dedupe because of unique constraints
                    if (isset($data['deviceId'])) {
                        $registry->setDeviceId($data['deviceId']);
                    }

                } catch (MapperException $e) {
                    $this->getResponse()->status->setCode(400);
                    $response->set([
                        'format' => 'json',
                        'data'   => 'Invalid app ID given',
                    ]);
                    return;
                }
            }

            $this->registryMapper->save($registry);
        }

        $response->set([
            'format' => 'json',
            'data'   => [
                'id'   => $sessionUserId,
                'key'  => $sessionUserKey,
                'role' => $sessionUser->getRole()->getType(),
            ],
        ]);
    }


    /**
     * @param array $array
     * @param array $keys
     */
    protected function assertArrayKeys(array $array, array $keys)
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                throw new \UnderflowException($key);
            }
        }
    }


    /**
     * Authenticates a user given the username/password in POST data.
     *
     * @noinspection PhpDocMissingThrowsInspection as \Aura\Web\Response\Status::setCode should never throw
     * @param array $postData Must contain "username" and "password" keys
     * @return User|null
     */
    private function authenticateViaPostData(array $postData): ?User
    {
        $response = $this->response;
        try {
            // require these to be present in POSTDATA
            $this->assertArrayKeys($postData, ['username', 'password']);

        } catch (\UnderflowException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $response->status->setCode(400);
            $response->content->set([
                'format' => 'json',
                'data'   => [
                    'required' => $e->getMessage(),
                ],
            ]);
            return null;
        }

        // verify username|password combination
        try {
            $this->auth->authenticate($postData['username'], $postData['password'], true);

            // only obtain these *after* you've run authentication
            return $this->getUser();
        } catch (SchoolboxAccessDeniedException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $response->status->setCode(401);
            $response->content->set([
                'format' => 'json',
                'data'   => 'Invalid username or password',
            ]);
            return null;
        } catch (SchoolboxAccountDisabledException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $response->status->setCode(400);
            $response->content->set([
                'format' => 'json',
                'data'   => 'Account disabled',
            ]);
            return null;
        } catch (SchoolboxNotFoundException $e) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $response->status->setCode(400);
            $response->content->set([
                'format' => 'json',
                'data'   => 'User not found',
            ]);
            return null;
        }
    }


    /**
     * Authenticates a user via a JWK passed in POST data.
     *
     * @param array $postData
     * @return User|null
     */
    private function authenticateViaJwk(array $postData): ?User
    {
        if (!isset($postData['jwt'])) {
            return null;
        }

        $config = $this->getConfigLoader();

        // TODO DI this once it starts being used in multiple places
        $tokenParser = new TokenParser(
            UserMapper::getInstance(),
            (new JwtVerifierBuilder())
                ->setIssuer($config->get(ConfigMapper::_CLOUDWORK_OAUTH_IDENTITY_PROVIDER))
                ->setAdaptor(new FirebasePhpJwt($config->get(ConfigMapper::_CLOUDWORK_OAUTH_JWT_KEY_ID)))
                ->setDiscovery(new Oidc())
                ->setAudience([$config->get(ConfigMapper::_CLOUDWORK_OAUTH_CLIENT_ID)])
                ->build()
        );
        return $tokenParser->findUserFromToken($postData['jwt']);
    }
}
