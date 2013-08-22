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

use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;

/**
 * Class Server
 * @package Proxy
 * @pattern Singleton
 */
class Server {

	const PROXY_AUTH_METHOD = CURLAUTH_BASIC;
	const PROXY_TYPE = CURLPROXY_HTTP;
	const PROXY_AUTH_STRING = "Basic";
	const PROXY_TYPE_STRING = "HTTP";

	protected static $instance;

	/**
	 * Return the Singleton ProxyServer instance.
	 *
	 * @return Server
	 */
	public static function getInstance() {
		if(!self::$instance) {
			self::$instance = new self(null);
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


	/**
	 * Constructor for ProxyServer. Accepts a config loader as a dependency.
	 *
	 * @param IConfigLoader $conf
	 */
	public function __construct($conf) {
		if(!$conf instanceof IConfigLoader) {
			$conf = new ConfigLoader();
		}
		$this->host = $conf->get("proxy_host");
		$this->port = (int)$conf->get("proxy_port");
		$this->user = $conf->get("proxy_user");
		$this->pass = $conf->get("proxy_pass");
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
		if($this->usesProxy()) {
			return $this->user && $this->pass;
		}
		return null;
	}

	/**
	 * Returns whether or not this is an anonymous proxy connection
	 * Convenience method
	 *
	 * @return bool
	 */
	public function isAnonymous() {
		if($this->usesProxy()) {
			return !$this->isAuthenticated();
		}
		return null;
	}


	/**
	 * Wrapper method for CURL resource creation, automatically sets proxy information
	 *
	 * @param null $hostname
	 * @return resource
	 */
	public function curl_init($hostname = null) {
		$c = curl_init($hostname);

		if($this->usesProxy()) {
			// global boolean
			curl_setopt($c, CURLOPT_HTTPPROXYTUNNEL, false);

			// currently constant values, may need to be overridden in future
			curl_setopt($c, CURLOPT_PROXYAUTH, self::PROXY_AUTH_METHOD);
			curl_setopt($c, CURLOPT_PROXYTYPE, self::PROXY_TYPE);
			curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_HEADER, 1);

			// configuration values
			curl_setopt($c, CURLOPT_PROXY, $this->getHost());
			curl_setopt($c, CURLOPT_PROXYPORT, $this->getPort());

			if($this->isAuthenticated()) {
				curl_setopt($c, CURLOPT_PROXYUSERPWD, $this->getUserPwd());
			}

		}

		return $c;
	}

	/**
	 * Returns to context resource for proxied streams
	 *
	 * @param string $protocol
	 * @param bool $asArray
	 * @return null|resource|array
	 */
	public function getContext($protocol = 'http', $asArray = false) {
		if(!$this->usesProxy()) {
			return null;
		}
		$transport = $protocol == 'https' ? 'ssl' : 'tcp';

		$opts[$protocol] = array(
			'proxy' => $transport . '://' . $this->getURI(),
			'request_fulluri' => true
		);

		if($this->isAuthenticated()) {
			$opts[$protocol]['header'] = array(
				'Proxy-Authorization: ' . self::PROXY_AUTH_STRING . ' ' . $this->getUserPwd(true)
			);
		}


		return $asArray ? $opts : stream_context_create($opts);
	}

}