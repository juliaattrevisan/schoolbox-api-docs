<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.edutcation/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\IsamsApi\Base\Endpoint;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use SensitiveParameter;
use Schoolbox\Url\Uri;

class Batchapi
{
    private array $jwks = [];
     private Api $api;

    /**
     * Batchapi constructor.
     *
     */
    public function __construct(
        private string $apiHost,
        private string $clientId,
        #[SensitiveParameter] private string $clientSecret,
    ) {
        // spawn a new api object
        $this->api = new Api(
            new HttpClient(),
            new Uri($apiHost)
        );
    }


    /**
     * Retrieve Bearer Token from the OIDC
     *
     * @return string
     * @throws ClientExceptionInterface if something went wrong fetching JWKS
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @throws \JsonException
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    public function getToken(): string
    {
        if (!empty($this->jwks) && $this->jwks['expires'] > time()) {
            return $this->jwks['access_token'];
        }
        // first, need to exchange client id/secret for an access token
        $this->jwks = $this->api->request(
            '/auth/connect/token',
            'POST',
            [
                RequestOptions::FORM_PARAMS => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                    'scope' => 'restapi',
                ],
            ]
        );

        $this->jwks['expires'] = time() + $this->jwks['expires_in'];

        return $this->jwks['access_token'];
    }

    /**
     * Make a request to the BatchAPI
     *
     * @param string $path
     * @param string $method
     * @param array $options
     * @param bool $raw // return the raw response body, NOT json decoded
     * @return mixed
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function request(string $path, string $method, array $options = [], bool $raw = false): mixed
    {
        return $this->api->request($path, $method, $options, $this->getToken(), $raw);
    }
}
