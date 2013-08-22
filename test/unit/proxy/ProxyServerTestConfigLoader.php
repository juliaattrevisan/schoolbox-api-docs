<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class ProxyServerTestConfigLoader implements \Schoolbox\IConfigLoader {

	protected $auth = array();

	public function __construct($host, $port, $user, $pass) {
		$this->auth = array(
			'proxy_host' => $host,
			'proxy_port' => $port,
			'proxy_user' => $user,
			'proxy_pass' => $pass
		);
	}

	public function get($var) {
		if(isset($this->auth[$var])) {
			return $this->auth[$var];
		}
		return null;
	}
}