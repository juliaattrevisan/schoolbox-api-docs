<?php

/**
 * Parameters
 *
 * Get the parameters for every request to help
 * Rammed into this class so that
 *
 * a) it's always built in the same way, in the same place, every time
 * b) we can swap the mechanism out for some other implementation if we want
 * c) hides away all these details from controllers, which are more strategic than detailed
 *
 * Usage:
 *
 * (new Parameters(
 *     $this->getConfigLoader()->get('schoolbox_help_url'),
 *     $this->getConfigLoader()->get('site_school_code'),
 *     $this->getConfigLoader()->get('schoolbox_help_access_token')
 * ))->generate();
*
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\SHAPS\Auth;

class Parameters
{

    /**
     * The url of help.schoolbox.com.au
     *
     * @var string
     */
    private $url;

    /**
     * This installation's site_school_code value
     *
     * @var string
     */
    private $schoolCode;

    /**
     * This installation's schoolbox_help_access_token
     *
     * @var string
     */
    private $accessToken;

    /**
     * Parameters constructor.
     *
     *
     * @param string $url
     * @param string $schoolCode
     * @param string $accessToken
     */
    public function __construct(string $url, string $schoolCode, string $accessToken)
    {
        $this->url = $url;
        $this->schoolCode = $schoolCode;
        $this->accessToken = $accessToken;
    }

    /**
     * Helper method to get details required for any Help API request.
     *
     * @return array
     */
    public function generate(): array
    {
        /* Query params that we need to provide in every request to ensure we're who we say we are
         */
        $schoolCode = strtolower($this->schoolCode);
        $accessToken = $this->accessToken;
        $timestamp = \Alaress_Date_Format::now()->getUnixTimeStamp();

        /* Generate a special piece of data derived from those three query param ingredients
         */
        $digest = (new Digest($schoolCode, $accessToken))->generate($timestamp);

        /* Return it all in a canonical structure
         */
        return [
            'url'        => $this->getServerUrl(),
            'timestamp'  => $timestamp,
            'digest'     => $digest,
            'schoolCode' => $schoolCode,
            'host'       => parse_url($this->url, PHP_URL_HOST),
        ];
    }

    /**
     * Officially get the url with trailing slash removed, if it existed
     *
     * @return string
     */
    public function getServerUrl(): string
    {
        return rtrim($this->url, '/');
    }
}
