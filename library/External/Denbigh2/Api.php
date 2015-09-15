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
     * Because sometimes columns get renamed randomly, databases
     * copied incompletely etc. store the generated url request
     * for debugging in $api->getErrors()
     *
     * @var string
     */
    private $generatedRequestUrl = '';

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
        $this->setHost(\Cfg::get('external_db_host'));
        $this->setKey(\Cfg::get('external_db_passwd'));

        $this->generateGetRequest($type, $keyvalues, $options);

        $curl = new \Alaress_Curl_Url($this->getGeneratedRequestUrl());
        $this->setCurl($curl)->getCurl()->exec();
    }

    /**
     * Manufacture url
     *
     * @param $type
     * @param $keyvalues
     * @param $options
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

            /* Note that Filemaker performs a LIKE statement by default
             * Use double-encoded equals sign to force exact matching
             * Advised by Gort
             */
            $mungedValues[$keystring] = $keys[$i];
            if ($values[$i] instanceof Alaress_Date_Format) {
                $mungedValues[$valuestring] = $values[$i]->getUsingFormat('d/m/Y');
            } else {
                $mungedValues[$valuestring] = "=={$values[$i]}";
            }
        }

        $dataset = array_merge($authentication, $mungedValues, $options);

        /* Implode all that
         */
        $querystring = http_build_query($dataset);

        $this->setGeneratedRequestUrl("{$url}?{$querystring}");
    }

    /**
     * Include filemaker errors
     * These are stored in a json encoded object
     *
     * stdClass Object
        (
            [X-RESTfm-FM-Status] => 102
            [X-RESTfm-FM-Reason] => Field is missing
            [X-RESTfm-Version] => 3.2.0/r498
            [X-RESTfm-Protocol] => 5
            [X-RESTfm-Status] => 500
            [X-RESTfm-Reason] => FileMaker Error
            [X-RESTfm-Method] => GET
            [X-RESTfm-Trace] => exception 'FileMakerResponseException' in /Library/FileMaker Server/HTTPServer/htdocs/RESTfm/lib/RESTfm/BackendFileMaker/FileMakerOpsLayout.php:96
        Stack trace:
        #0 /Library/FileMaker Server/HTTPServer/htdocs/RESTfm/lib/uriLayout.php(119): FileMakerOpsLayout->read()
        #1 [internal function]: uriLayout->get(Object(RESTfmRequest), 'Staging', 'Students')
        #2 /Library/FileMaker Server/HTTPServer/htdocs/RESTfm/lib/tonic/lib/tonic.php(697): call_user_func_array(Array, Array)
        #3 /Library/FileMaker Server/HTTPServer/htdocs/RESTfm/lib/RESTfm/RESTfmResource.php(49): Resource->exec(Object(RESTfmRequest))
        #4 /Library/FileMaker Server/HTTPServer/htdocs/RESTfm/RESTfm.php(91): RESTfmResource->exec(Object(RESTfmRequest))
        #5 {main}
        )
     */
    private function hasFilemakerError() {
        $filemakerError = json_decode($this->getCurl()->getResult());

        if ($filemakerError) {
            if (isset($filemakerError->info->{'X-RESTfm-Reason'}) && ($filemakerError->info->{'X-RESTfm-Reason'} == 'FileMaker Error')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the whole object
     *
     * @return array
     */
    private function getFilemakerError() {
        $filemakerError = json_decode($this->getCurl()->getResult());

        return array(
            'request' => $this->getGeneratedRequestUrl(),
            'response' => $filemakerError->info,
        );
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

        if ($this->hasFilemakerError()) {
            return true;
        }

        return false;
    }

    /**
     * Get curl error object
     *
     * Augment with filemaker fails
     *
     * @return mixed
     */
    public function getErrors() {
        $allErrors = $this->getCurl()->getErrors();

        if ($this->hasFilemakerError()) {
            $allErrors = array_merge(
                $this->getCurl()->getErrors(),
                $this->getFilemakerError()
            );
        }

        return $allErrors;
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

    /**
     * Set generated request url
     *
     * @param string $generatedRequestUrl
     */
    private function setGeneratedRequestUrl($generatedRequestUrl)
    {
        $this->generatedRequestUrl = $generatedRequestUrl;
    }

    /**
     * Get generated request url
     *
     * @return string
     */
    public function getGeneratedRequestUrl()
    {
        return $this->generatedRequestUrl;
    }
}