<?php

namespace External\Tass\Portal;

use UnexpectedValueException;
use GuzzleHttp;
use InvalidArgumentException;

/**
 * TASSWeb SSO token retriever
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
*/
class SSO
{
    // How should we attempt to generate an SSO token?
    // Corresponds to DB config setting
    public const SSO_STRATEGY_V1_ONLY    = 1;
    public const SSO_STRATEGY_V2_ONLY    = 2;
    public const SSO_STRATEGY_V2_THEN_V1 = 3;
    public const SSO_STRATEGY_V3_SAML    = 4;

    // If not configured in db, what should the default strategy be?
    public const SSO_STRATEGY_DEFAULT    = self::SSO_STRATEGY_V2_THEN_V1;

    // Recognised TASS SSO token request response codes and what they mean
    private const RESPONSE_CODES = [
        1 => 'Ok',
        2 => 'URL parameters missing.',
        3 => 'Invalid SSO Token Key, or SSO Application Code, or SSO User Mapping, or IP is not white-listed',
        4 => 'User not recognised',
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
     * Which SSO token generation strategy should we use?
     *
     * @var int
     * @see self::SSO_STRATEGY_* constants
     */
    private $ssoStrategy;


    /**
     * Returns the set of available SSO strategies
     *
     * @return int[]
     */
    public static function getSsoStrategies(): array
    {
        return [
            self::SSO_STRATEGY_V1_ONLY,
            self::SSO_STRATEGY_V2_ONLY,
            self::SSO_STRATEGY_V2_THEN_V1,
            self::SSO_STRATEGY_V3_SAML,
        ];
    }


    /**
     * Borrowed this default+override configuration idea from the js idiom
     *
     * @param array  $configuration Overrides for $this->configuration (if any)
     *                              Should contain at least 'token_key'.
     *
     * @param string $server
     * @param int    $ssoStrategy
     * @throws InvalidArgumentException If an unrecognised SSO strategy is specified
     */
    public function __construct(array $configuration, string $server, int $ssoStrategy)
    {
        $this->configuration = array_merge($this->configuration, $configuration);
        $this->server        = $server;
        if (!\in_array($ssoStrategy, self::getSsoStrategies(), true)) {
            throw new InvalidArgumentException("Invalid SSO token generation strategy: '{$ssoStrategy}'");
        }
        $this->ssoStrategy = $ssoStrategy;
    }


    /**
     * Gets a user token.
     *
     * @param array      $userConf   Overrides for $this->configuration (if any)
     *                               Should contain at least ['sso_code' => $tassID]
     * @param array      $deepLinkV2 Injected when requesting a SSOv2 token
     * @param bool       $prodMenu
     * @param array|null $deepLinkV1 Injected when requesting a SSOv1 token
     * @return string If $this->output is true, this will output a string to
     *                            stdout, and then exit.
     * @throws SsoTokenGenerationException
     * @throws \RuntimeException
     */
    public function getUserToken(array $userConf, array $deepLinkV2, bool $prodMenu, ?array $deepLinkV1 = null): string
    {
        $conf   = array_merge($this->configuration, $userConf);
        $client = new GuzzleHttp\Client();
        $errors = [];

        // Is school configured to attempt SSO v2 ?
        if (\in_array($this->ssoStrategy, [self::SSO_STRATEGY_V2_ONLY, self::SSO_STRATEGY_V2_THEN_V1], true)) {
            try {
                return $this->getV2Token($client, $conf, $deepLinkV2, $prodMenu);
            } catch (UnexpectedValueException $e) {
                $errors[] = "SSO v2: {$e->getMessage()}";
            }
        }

        // Is school configured to attempt SSO v1 ?
        if (\in_array($this->ssoStrategy, [self::SSO_STRATEGY_V1_ONLY, self::SSO_STRATEGY_V2_THEN_V1], true)) {
            try {
                return $this->getV1Token($client, $conf, $deepLinkV1);
            } catch (UnexpectedValueException $e) {
                $errors[] = "SSO v1: {$e->getMessage()}";
            }
        }

        // Merge exception messages
        throw new SsoTokenGenerationException('Unable to generate SSO token. ' . implode(', ', $errors));
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
        ]);

        // Don't pass a deep-link param is no deep-link is being requested
        if (!empty($deepLink)) {
            $formParams['deep_link'] = \json_encode($deepLink);
        }

        $response       = $client->request('POST', "{$this->server}?returnType=json", ['form_params' => $formParams]);
        $responseString = $response->getBody()->getContents();

        if ($this->debug) {
            header('Content-Type: text/plain');
            $data = print_r($formParams, true);
            die("SSO v2.\nPOST: {$this->server}?returnType=json\nData: {$data}\nRECV: {$responseString}\n");
        }

        // The response should be in this format
        $responseData = json_decode($responseString, true);

        if (!\is_array($responseData)) {
            // For some unknown reason, 'SAAGS Dev' is returning a plain string, while other instances are returning a json encoded array
            parse_str($responseString, $responseData);
        }

        // Still not expected format?
        if (!\is_array($responseData)) {
            // Just throw it back
            throw new SsoTokenGenerationException("Unable to process response: '{$responseString}'");
        }

        return $this->parseResponse($responseData, $conf['sso_code']);
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
            throw new SsoTokenGenerationException("{$errorMessage} for SSO with ID: {$ssoCode}");
        }

        return '';
    }


    /**
     * @param GuzzleHttp\Client $client
     * @param array             $conf
     * @param array|null        $deepLinkV1
     * @return string
     * @throws SsoTokenGenerationException
     * @throws \RuntimeException
     */
    private function getV1Token(GuzzleHttp\Client $client, array $conf, ?array $deepLinkV1 = null): string
    {
        // Don't include a deep-link arg if no deep-link is being requested
        if (\is_array($deepLinkV1) && !empty($deepLinkV1)) {
            $conf['deep_link'] = 'index.cfm?' . http_build_query($deepLinkV1);
        }
        $query = http_build_query($conf);
        $responseString = $client->get("{$this->server}?{$query}")->getBody()->getContents();
        parse_str($responseString, $response);

        if ($this->debug) {
            header('Content-Type: text/plain');
            die("SSO v1.\nSEND: {$this->server}?{$query}\nRECV: {$responseString}\n");
        }
        return $this->parseResponse($response, $conf['sso_code']);
    }
}
