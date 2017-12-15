<?php
/**
 * Alaress_Curl_Url
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Curl_Url {
	private $errors = array();
	private $result = '';

    private $responseCode = 0;

	/* Default options
	 */
	private $options = array(
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => 1,
	);

	/* Curl source url
	 */
	private $url = '';

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($url) {
		$this->setUrl($url);
	}

	/**
	 * Add implementation-specific options
	 *
	 * @param $newOptions
	 * @return \Alaress_Curl_Url
	 */
	public function addOptions($newOptions) {

		/* Use array union operator to glue these two arrays together
		 * and preserve indexes. array_merge() loses numerical indexes
		 *
		 * http://php.net/manual/en/language.operators.array.php
		 */
		$this->setOptions($this->getOptions() + $newOptions);

		return $this;
	}

	/**
	 * Execute curl request and store result
	 *
	 * @return \Alaress_Curl_Url
	 */
	public function exec() {

		$ch = \Proxy\Server::getInstance()->curlInit($this->getUrl());

		curl_setopt_array($ch, $this->getOptions());

		$this->setResult(curl_exec($ch));
		$this->addErrors(curl_error($ch));
		$this->setResponseCode(curl_getinfo($ch, CURLINFO_HTTP_CODE));

		curl_close($ch);

		return $this;
	}

	/**
	 * Add errors
	 *
	 * @param $error
	 */
	private function addErrors($error) {
		if (strlen($error)) {
			$this->errors[] = $error;
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Get errors array
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Set curl result
	 *
	 * @param $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * Get curl result
	 *
	 * @return string
	 */
	public function getResult() {
		return $this->result;
	}

    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @param  int $responseCode
     * @return $this
     */
    private function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
        return $this;
    }

	/**
	 * Set url
	 *
	 * @param $url
	 */
	private function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	private function getUrl() {
		return $this->url;
	}

	/**
	 * Set options
	 *
	 * @param $options
	 */
	private function setOptions($options) {
		$this->options = $options;
	}

	/**
	 * Get options
	 *
	 * @return array
	 */
	private function getOptions() {
		return $this->options;
	}
}
