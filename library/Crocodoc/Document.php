<?php
/**
 * Document
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class Document {

	/**
	 * Contains API keys
	 *
	 * @var null
	 */
	private $authenticator = null;

	/**
	 * The url of the file to upload
	 *
	 * @var string
	 */
	private $url = '';

	/**
	 * Constructor
	 */
	public function __construct($url) {
		$this->setAuthenticator(new Authenticator());
		$this->setUrl($url);
	}

	/**
	 * Upload a file to crocodoc
	 */
	public function upload() {
		$curl = new \Alaress_Curl_Url('https://crocodoc.com/api/v2/document/upload');

		$postfields = array();
		$postfields['token'] = $this->getAuthenticator()->getApiToken();
		$postfields['file'] = '@' . $this->getUrl();
		$postfields['filename'] = 'mort.pdf';

		$curl->addOptions(array(
			CURLOPT_HTTPHEADER => array('Content-Type: multipart/form-data'),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $postfields,
			CURLOPT_FOLLOWLOCATION => 1,
		));

		$curl->exec();

		return json_decode($curl->getResult());
	}


	/* Getters/Setters
	 */

	/**
	 * Set authenticator
	 *
	 * @param null $authenticator
	 */
	private function setAuthenticator($authenticator) {
		$this->authenticator = $authenticator;
	}

	/**
	 * Get authenticator
	 *
	 * @return null
	 */
	private function getAuthenticator() {
		return $this->authenticator;
	}

	/**
	 * Set url
	 *
	 * @param string $url
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
}