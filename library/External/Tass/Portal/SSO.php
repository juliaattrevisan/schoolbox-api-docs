<?php

namespace External\Tass\Portal;

use UnexpectedValueException;
use GuzzleHttp;

/**
 * TASSWeb SSO token retriever
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class SSO
{

    // Recognised TASS SSO token request response codes and what they mean
    private const RESPONSE_CODES = [
        1 => 'Ok',
        2 => 'URL parameters missing.',
        3 => 'Invalid SSO Application Code or SSO User Mapping',
        4 => 'Invalid Portal User',
        5 => 'Invalid sfa_num',
    ];

    /**
     * Is the response from the SSO server output? If not, getUserToken()
     * returns its token.
     *
     * @var bool
     */
    private $debug = false;

    /**
     * The server from which to retrieve a SSO token
     *
     * @var string
     */
    private $server;

    /**
     * Default configuration, can be overridden in __construct() or getUserToken()
     *
     * @var array
     */
    private $configuration = [
        'method' => 'createSSOToken',
    ];


    /**
     * Borrowed this default+override configuration idea from the js idiom
     *
     * @param array  $configuration Overrides for $this->configuration (if any)
     *                              Should contain at least 'token_key'.
     *
     * @param string $server
     */
    public function __construct(array $configuration, string $server)
    {
        $this->configuration = array_merge($this->configuration, $configuration);
        $this->server        = $server;
    }


    /**
     * Gets a user token.
     *
     * @param array $userConf     Overrides for $this->configuration (if any)
     *                            Should contain at least ['sso_code' => $tassID]
     *
     * @param array $deepLink
     * @param bool  $prodMenu
     * @return string If $this->output is true, this will output a string to
     *                            stdout, and then exit.
     * @throws SsoTokenGenerationException
     * @throws \RuntimeException
     */
    public function getUserToken(array $userConf, array $deepLink, bool $prodMenu): string
    {
        $conf   = array_merge($this->configuration, $userConf);
        $client = new GuzzleHttp\Client();

        // First attempt to get a token using SSO v2
        try {
            return $this->getV2Token($client, $conf, $deepLink, $prodMenu);
        } catch (UnexpectedValueException $v2e) {
        }

        // Then attempt to get a token using SSO v1
        try {
            return $this->getV1Token($client, $conf);
        } catch (UnexpectedValueException $v1e) {
        }

        // Merge exceptions if both SSO versions failed
        throw new SsoTokenGenerationException("SSO v1: {$v1e->getMessage()}, SSO v2: {$v2e->getMessage()}");
    }


    /**
     * @param GuzzleHttp\Client $client
     * @param array             $conf
     * @param string[]          $deepLink To which page in the TASS product should TASS redirect after auth?
     * @param bool              $prodMenu Should we display the product menu heading? Ignored if $deepLink is invalid
     * @return string
     * @throws SsoTokenGenerationException
     * @throws \RuntimeException
     */
    private function getV2Token(GuzzleHttp\Client $client, array $conf, array $deepLink, bool $prodMenu): string
    {
        $formParams = array_merge($conf, [
            'prod_menu' => $prodMenu ? 'Y' : 'N',
            'deep_link' => json_encode($deepLink),
        ]);

        $response       = $client->request('POST', "{$this->server}?returnType=json", ['form_params' => $formParams]);
        $responseString = $response->getBody()->getContents();

        if ($this->debug) {
            header('Content-Type: text/plain');
            $queryString = http_build_query($formParams);
            die("SSO v2.\nPOST: {$this->server}?returnType=json\nData: {$queryString}\nRECV: {$responseString}\n");
        }

        return $this->parseResponse(json_decode($responseString, true), $conf['sso_code']);
    }


    /**
     * @param array  $response
     * @param string $ssoCode
     * @return string
     * @throws SsoTokenGenerationException
     */
    private function parseResponse(array $response, string $ssoCode): string
    {
        if (isset($response['sso_token'])) {
            return $response['sso_token'];
        }

        if (isset($response['response_code']) && array_key_exists($response['response_code'], self::RESPONSE_CODES)) {
            $errorMessage = self::RESPONSE_CODES[$response['response_code']];
            throw new SsoTokenGenerationException("TASS SSO Error: {$errorMessage} for SSO for user {$ssoCode}");
        }

        return '';
    }


    /**
     * @param GuzzleHttp\Client $client
     * @param array             $conf
     * @return string
     * @throws SsoTokenGenerationException
     * @throws \RuntimeException
     */
    private function getV1Token(GuzzleHttp\Client $client, array $conf): string
    {
        $responseString = $client->get($this->server, ['query' => $conf])->getBody()->getContents();
        parse_str($responseString, $response);

        if ($this->debug) {
            header('Content-Type: text/plain');
            $data = http_build_query($conf);
            die("SSO v1.\nSEND: {$this->server}?{$data}\nRECV: {$responseString}\n");
        }
        return $this->parseResponse($response, $conf['sso_code']);
    }
}
