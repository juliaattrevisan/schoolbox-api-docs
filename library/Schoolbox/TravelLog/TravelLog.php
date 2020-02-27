<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\TravelLog;

use Alaress\Redis;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 *
 * Helper class for making API calls to Verso Gateway API.
 *
 */
class TravelLog
{

    // The Verso Gateway API URL for SchoolBox
    private const API_URL = 'https://gateway-staging.verso.school/';

    // Verso Gateway API URL to get token
    private const VERSO_TOKEN_URL = 'https://verso-gateway-staging.auth.ap-southeast-1.amazoncognito.com/oauth2/token';

    // Path for student's locations
    private const LOCATION_LOG_PATH = 'location/student/';

    // Verso Grant Type
    private const GRANT_TYPE = 'clientCredentials';

    /**
     * @var GenericProvider
     */
    private $provider;

    /**
     * @var Redis
     */
    private $cache;


    /**
     * TravelLog constructor.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param Redis  $cache
     */
    public function __construct(string $clientId, string $clientSecret, Redis $cache)
    {
        // Initiate provider for Verso Gateway OAuth authentication
        $this->provider = new GenericProvider([
            'clientId'                => $clientId,
            'clientSecret'            => $clientSecret,
            'urlAuthorize'            => self::API_URL,
            'redirectUri'             => self::API_URL,
            'urlAccessToken'          => self::VERSO_TOKEN_URL,
            'urlResourceOwnerDetails' => self::API_URL,
        ]);

        $this->cache = $cache;
    }

    /**
     * Gets the token from Verso Gateway API for the requests
     *
     * @return AccessTokenInterface
     * @throws IdentityProviderException
     */
    private function getToken(): AccessTokenInterface
    {
        return $this->provider->getAccessToken(self::GRANT_TYPE);
    }


    /**
     * Gets the travel log details of the student
     *
     * @param string $externalId
     * @return false|string
     * @throws IdentityProviderException
     */
    public function getForStudent($externalId)
    {
        $request = $this->provider->getAuthenticatedRequest(
            'GET',
            self::API_URL . self::LOCATION_LOG_PATH . $externalId,
            $this->getToken()
        );
        return $this->provider->getParsedResponse($request);
    }

    /**
     * Caches the student travel logs details
     *
     * @param string $externalId
     * @param int    $timeout
     * @return mixed
     */
    public function getForStudentCached(string $externalId, int $timeout)
    {
        $callback = function ($params) {
            return $this->getForStudent($params);
        };

        return $this->cache->cacheFunctionCall('verso_api', $callback, $externalId, $timeout);
    }

}
