<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Remote;

use GuzzleHttp\ClientInterface;
use Schoolbox\Realtime\Pipe\PipeManager;
use Laminas\Uri\UriInterface;

/**
 * Class for creating a session which is authenticated as a particular user.
 *
 * The main use case for this is for creating automated web crawlers that are
 * authenticated as a user.
 */
class RemoteLogin
{
    /**
     * A HTTP client, for requests to the Schoolbox instance on `$this->domain`.
     *
     * @var ClientInterface
     */
    private $httpClient;


    /**
     * The URL on which the Schoolbox instance is hosted.
     *
     * @var UriInterface
     */
    private $baseUrl;


    /**
     * Constructor
     *
     * @param ClientInterface $httpClient
     * @param UriInterface    $baseUrl
     */
    public function __construct(ClientInterface $httpClient, UriInterface $baseUrl)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl    = $baseUrl;
    }


    /**
     * Authenticates a given user/password combination into the Schoolbox
     * instance.
     *
     * @param string $username
     * @param string $password
     * @return string|null The session cookie value, or null if none was found
     *                     (i.e. if auth failed).
     */
    public function authenticate(string $username, string $password): ?string
    {
        $url      = $this->baseUrl->setPath('/login')->toString();
        $headers  = ['Origin' => $this->baseUrl->getHost()];
        $postData = [
            'page'     => '/login/',
            'username' => $username,
            'password' => $password,
            'Submit'   => 'Sign in',
        ];

        $options = [
            'headers'         => $headers,
            'form_params'     => $postData,
            'allow_redirects' => false,
        ];

        $response = $this->httpClient->request('POST', $url, $options);
        if (303 !== $response->getStatusCode()) {
            return null;
        }

        $cookieName = PipeManager::PHP_SESSION_PREFIX;
        foreach ($response->getHeader('Set-Cookie') as $cookieString) {
            if (preg_match("/^{$cookieName}=([a-z0-9]+);/", $cookieString, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}
