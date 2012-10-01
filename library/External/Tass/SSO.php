<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class External_Tass_SSO {

    private $server = 'http://tass-server/tassweb/external/sso_gateway.cfm';

    private $configuration = array(
        'method' => 'createSSOToken',
        'cmpy_code' => '01',
        'prod_code' => 'PL',
        'app_code' => 'pl',
        // 'sso_code' => $tassID,
        'token_key' => 'L@g00n1917',
        'returntype' => 'URLPARAM'
    );

    /**
     * Borrowed this default+override configuration idea from the js idiom
     * @param array $configuration
     */
    public function __construct($configuration = array(), $server = '') {
        if(count($configuration)) {
            foreach($configuration as $key => $value) {
                $this->configuration[$key] = $value;
            }
        }
        if($server) {
            $this->server = $server;
        }
    }

    /**
     * @param array $userConf array('sso_code' => $tassID)
     * @return string|null
     */
    public function getUserToken(array $userConf) {
        $conf = array_merge($this->configuration, $userConf);
        $cString = $this->array2qstring($conf);

        $target = $this->server . '?' . $cString;

        // attempt sso authentication
        $responseString = @file_get_contents($target);

        $response = $this->qstring2array($responseString);

        if(isset($response['sso_token'])) {
            return $response['sso_token'];
        }
        return null;
    }



    /**
     * Convert an array to a GET query string
     * @param $array
     * @return string
     */
    public function array2qstring($array) {
        $cPairs = array();
        while($i = each($array)) {
            $cPairs[] = $i['key'] . '=' . $i['value'];
        }
        return implode('&', $cPairs);
    }

    /**
     * Convert a GET query string to an array
     * @param $qstring
     * @return array
     */
    public function qstring2array($qstring) {
        $array = array();
        $parts = explode('&', $qstring);

        foreach($parts as $part) {
            $bits = explode('=', $part);
            if(count($bits == 2)) {
                $array[$bits[0]] = $bits[1];
            }
        }
    }

}
