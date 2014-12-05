<?php
/**
 * Denbigh2Curl
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Denbigh2_Api {

    /**
     * Where denbigh lives
     *
     * @var string
     */
    private $host = 'http://125.255.100.142/RESTfm/Staging/layout';

    /**
     * RFMkey
     *
     * @var string
     */
    private $key = 'DenbighKey2014denb1gh';

    /**
     * An instance of Alaress_Curl_Url
     *
     * @var \Alaress_Curl_Url
     */
    private $curl = null;

    /**
     * $url is the data file ie. Students.json, Staff.json etc
     * $keyvalues is a key-value array
     *
     * Curly whirly upon instantiation
     *
     * @param $url
     * @param $keyvalues
     */
    public function __construct($url, $keyvalues) {
        $curl = new \Alaress_Curl_Url($this->generateGetRequest($url, $keyvalues));

        $this->setCurl($curl)->getCurl()->exec();
    }

    /**
     * Manufacture url
     *
     * @param $url
     * @param $keyvalues
     * @return string
     */
    private function generateGetRequest($url, $keyvalues) {
        $url = "{$this->getHost()}/{$url}";

        /* Merge in authentication stuff
         */
        $authentication = array(
            'RFMkey' => $this->getKey()
        );

        $dataset = array_merge($authentication, $keyvalues);

        /* Implode all that
         */
        $querystring = http_build_query($dataset);

        return "{$url}?{$querystring}";
    }

    /**
     * Get curl error object
     *
     * @return mixed
     */
    public function getErrors() {
        return $this->getCurl()->getErrors();
    }

    /**
     * Get curl result object
     *
     * @return mixed
     */
    public function getResult() {
        return $this->getCurl()->getResult();
    }

    /* Getters/Setters
     */

    /**
     * Set curl
     *
     * @param \Alaress_Curl_Url $curl
     * @return $this
     */
    private function setCurl($curl)
    {
        $this->curl = $curl;

        return $this;
    }

    /**
     * Get curl
     *
     * @return \Alaress_Curl_Url
     */
    private function getCurl()
    {
        return $this->curl;
    }

    /**
     * Set host
     *
     * @param string $host
     */
    private function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * Get host
     *
     * @return string
     */
    private function getHost()
    {
        return $this->host;
    }

    /**
     * Set key
     *
     * @param string $key
     */
    private function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Get key
     * 
     * @return string
     */
    private function getKey()
    {
        return $this->key;
    }
}