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
    private $host = 'http://10.1.0.9/RESTfm/Staging/layout';

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
     * $options is an array of query options such as offsets,
     * limits, min/max settings and other undocumented novelties
     * These don't conform to RFMsF<n> and RFMsV<n> structure, so
     * they need to be amalgamated separately if provided
     *
     * Curly-whirl upon instantiation
     *
     * @param $type
     * @param $keyvalues
     * @param $options
     */
    public function __construct($type, $keyvalues, $options = array()) {
        $curl = new \Alaress_Curl_Url($this->generateGetRequest($type, $keyvalues, $options));

        $this->setCurl($curl)->getCurl()->exec();
    }

    /**
     * Manufacture url
     *
     * @param $type
     * @param $keyvalues
     * @param $options
     * @return string
     */
    private function generateGetRequest($type, $keyvalues, $options) {

        /* Explicitly use the .json return structure
         */
        $url = "{$this->getHost()}/{$type}.json";

        /* Merge in authentication stuff
         */
        $authentication = array(
            'RFMkey' => $this->getKey()
        );

        /* Munge up the $keyvalues into the format expected,
         * using RFMsF and RFMsV keys and values because yeah
         *
         * So I decided to make it easier to use, with just normal
         * key-value pairs being passed in here for processing
         */
        $keys = array_keys($keyvalues);
        $values = array_values($keyvalues);

        $mungedValues = array();

        for ($i=0; $i<count($keys); $i++) {
            $index = $i+1;

            $keystring = "RFMsF{$index}";
            $valuestring = "RFMsV{$index}";

            $mungedValues[$keystring] = $keys[$i];
            $mungedValues[$valuestring] = $values[$i];
        }

        $dataset = array_merge($authentication, $mungedValues, $options);

        /* Implode all that
         */
        $querystring = http_build_query($dataset);

        return "{$url}?{$querystring}";
    }

    /**
     * Decide if this curl request had errors
     *
     * @return bool
     */
    public function hasErrors() {
        if (count($this->getErrors())) {
            return true;
        }

        return false;
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
        return json_decode($this->getCurl()->getResult());
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