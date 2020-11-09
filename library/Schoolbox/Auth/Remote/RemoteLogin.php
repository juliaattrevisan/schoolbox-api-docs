<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth\Remote;

use GuzzleHttp\ClientInterface;
use Schoolbox\Realtime\Pipe\PipeManager;
use Zend\Uri\Uri;

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
     * The domain name on which the Schoolbox instance is hosted.
     *
     * @var string
     */
    private $domain;


    /**
     * The port on which this instance is accessible, null to use the standard
     * HTTPS port.
     *
     * @var int|null
     */
    private $port;


    /**
     * Constructor
     *
     * @param ClientInterface $httpClient
     * @param string          $host
     * @param int|null        $port
     */
    public function __construct(ClientInterface $httpClient, string $host, ?int $port = null)
    {
        $this->httpClient = $httpClient;
        $this->domain     = $host;
        $this->port       = $port;

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
        $url = (new Uri())
            ->setScheme('https')
            ->setHost($this->domain)
            ->setPath('/login');
        if (isset($this->port)) {
            $url = $url->setPort($this->port);
        }

        $headers  = ['Origin' => $this->domain];
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

        $response = $this->httpClient->request('POST', $url->toString(), $options);
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
