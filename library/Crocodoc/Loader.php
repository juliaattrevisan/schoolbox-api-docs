<?php
/**
 * Session
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc;

class Loader extends Base {

	/**
	 * The user doing the marking
	 *
	 * @var null
	 */
	private $user = null;

	/**
	 * A unique identifier for a resource uploaded to Crocodoc
	 * @var string
	 */
	private $uuid = '';

	/**
	 * Constructor
	 *
	 * @param $uuid
	 * @param \Schoolbox\User $user
	 */
	public function __construct($uuid, \Schoolbox\User $user) {
		$this->setUuid($uuid);
		$this->setUser($user);
	}

	/**
	 * Get the url used to produce the crocodoc document for annotation
	 */
	private function getDocumentUrl() {
		$userTuple = "{$this->getUser()->getId()},{$this->getUser()->getUsername()}";

		$curl = new \Alaress_Curl_Url('https://crocodoc.com/api/v2/session/create');

		$curl->addOptions(array(
			CURLOPT_POSTFIELDS => "token={$this->getApiToken()}&uuid={$this->getUuid()}&editable=true&user={$userTuple}",
			CURLOPT_POST => true,
		));

		$curl->exec();

		return json_decode($curl->getResult());
	}

	/**
	 * Spawn an iframe
	 */
	public function render() {
		$result = $this->getDocumentUrl();

		echo "https://crocodoc.com/view/{$result->session}";
	}

	/* Getters/Setters
	 */

	/**
	 * SEt user
	 *
	 * @param null $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return null
	 */
	private function getUser() {
		return $this->user;
	}

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
}