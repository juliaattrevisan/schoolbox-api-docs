<?php
/**
 * Encapsulating class to provide details about how Schoolbox is configured to interact with proxy servers
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Proxy;

/**
 * Class Server
 * @package Proxy
 * @pattern Singleton
 */
class Server {

	const PROXY_AUTH_METHOD = CURLAUTH_BASIC;
	const PROXY_TYPE = CURLPROXY_HTTP;

	protected static $instance;

	/**
	 * @return Server
	 */
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/** @var string */
	protected $host;
	/** @var int */
	protected $port;
	/** @var string */
	protected $user;
	/** @var string */
	protected $pass;

	protected function __construct() {
		$this->host = \Config::get("proxy_host");
		$this->port = (int)\Config::get("proxy_port");
		$this->user = \Config::get("proxy_user");
		$this->pass = \Config::get("proxy_pass");
	}

	/**
	 * Returns the hostname of the proxy server
	 *
	 * @return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * Returns the port of the proxy server
	 *
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * Returns the proxy host URI formatted as [host]:[port]
	 * Convenience method
	 *
	 * @return string
	 */
	public function getURI() {
		return $this->host . ':' . $this->port;
	}

	/**
	 * Returns the username Schoolbox uses to authenticate against the proxy server
	 *
	 * @return string
	 */
	public function getUsername() {
		return $this->user;
	}

	/**
	 * Returns the password Schoolbox uses to authenticate against the proxy server
	 *
	 * @return string
	 */
	public function getPassword() {
		return $this->pass;
	}

	/**
	 * Returns the USERPASSWD string used for proxy authentication, formatted as [username]:[password]
	 * Optionally base64 encoded
	 * Convenience method
	 *
	 * @param bool $base64encode
	 * @return string
	 */
	public function getUserPwd($base64encode = false) {
		$userpasswd = $this->user . ':' . $this->pass;
		if($base64encode) {
			return base64_encode($userpasswd);
		}
		return $userpasswd;
	}

	/**
	 * Returns whether or not Schoolbox is configured to use a proxy server
	 *
	 * @return bool
	 */
	public function usesProxy() {
		return $this->host && $this->port;
	}

	/**
	 * Returns whether or not this is an authenticated proxy connection
	 *
	 * @return bool
	 */
	public function isAuthenticated() {
		return $this->user && $this->pass;
	}

	/**
	 * Returns whether or not this is an anonymous proxy connection
	 * Convenience method
	 *
	 * @return bool
	 */
	public function isAnonymous() {
		return !$this->isAuthenticated();
	}


}