<?php
/**
 * TASSWeb SSO token retriever
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Tass_SSO
{

    /**
     * Is the response from the SSO server output? If not, getUserToken()
     * returns its token.
     *
     * @var bool
     */
    private $output = false;

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
    private $configuration = array(
        'method' => 'createSSOToken',
        'cmpy_code' => '01',
        'prod_code' => 'PL',
        'app_code' => 'pl',
        // 'sso_code' => $tassID,
        // 'token_key' => '',
        'returntype' => 'URLPARAM'
    );

    /**
     * Borrowed this default+override configuration idea from the js idiom
     *
     * @param array $configuration Overrides for $this->configuration (if any)
     *                             Should contain at least 'token_key'.
     *
     * @param string $server
     */
    public function __construct($configuration = array(), $server = '')
    {
        if (count($configuration)) {
            foreach ($configuration as $key => $value) {
                $this->configuration[$key] = $value;
            }
        }
        if ($server) {
            $this->server = $server;
        }
    }

    /**
     * Gets a user token.
     *
     * @param array $userConf Overrides for $this->configuration (if any)
     *                        Should contain at least ['sso_code' => $tassID]
     *
     * @return string If $this->output is true, this will output a string to
     *                stdout, and then exit.
     */
    public function getUserToken(array $userConf)
    {
        $conf = array_merge($this->configuration, $userConf);
        $cString = $this->array2qstring($conf);

        $target = $this->server . '?' . $cString;

        // attempt sso authentication
        $responseString = @file_get_contents($target);

        $response = $this->qstring2array($responseString);

        if ($this->output) {
            header('Content-Type: text/plain');
            die("SEND : $target\nRECV : $responseString\n");
        }

        if (isset($response['sso_token'])) {
            return $response['sso_token'];
        }
        return '';
    }



    /**
     * Convert an array to a GET query string
     * @param $array
     * @return string
     */
    public function array2qstring($array)
    {
        $cPairs = array();
        while ($i = each($array)) {
            $cPairs[] = $i['key'] . '=' . $i['value'];
        }
        return implode('&', $cPairs);
    }

    /**
     * Convert a GET query string to an array
     * @param $qstring
     * @return array
     */
    public function qstring2array($qstring)
    {
        $array = array();
        $parts = explode('&', $qstring);

        foreach ($parts as $part) {
            $bits = explode('=', $part);
            if (count($bits == 2)) {
                $array[$bits[0]] = $bits[1];
            }
        }
        return $array;
    }
}
