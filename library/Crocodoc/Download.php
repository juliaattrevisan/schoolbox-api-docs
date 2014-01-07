<?php
/**
 * Download
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;


class Download {

	/**
	 * Contains API keys
	 *
	 * @var null
	 */
	private $authenticator = null;

	/**
	 * The uuid of the crocodoc document
	 *
	 * @var string
	 */
	private $uuid = '';

	/**
	 * Constructor
	 *
	 * @param $uuid
	 */
	public function __construct($uuid) {
		$this->setAuthenticator(new Authenticator());
		$this->setUuid($uuid);
	}

	/**
	 * Download file and provide link to it
	 */
	public function start() {
		$curl = new \Alaress_Curl_Url("https://crocodoc.com/api/v2/download/document?token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid()}&annotated=true");

		$curl->exec();

		$data = $curl->getResult();

		$path = \Cfg::get('submission_upload_tmp_dir') . '/mort.pdf';

		file_put_contents($path, $data);

		echo json_encode(array(
			'path' => $path
		));
	}

	/* Getters/Setters
	 */

	/**
	 * Set uuid
	 *
	 * @param string $uuid
	 */
	private function setUuid($uuid) {
		$this->uuid = $uuid;
	}

	/**
	 * Get uuid
	 *
	 * @return string
	 */
	private function getUuid() {
		return $this->uuid;
	}

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
}