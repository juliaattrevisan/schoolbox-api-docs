<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class ConfigFallback implements \Schoolbox\IConfigLoader {

	protected $cfg;

	public function __construct() {
		$this->cfg = array(
			'mail_support_address'  => 'support@alaress.com.au',
			'logs_dir'              => SITE_root . '/data/cache/logs',
            'debug_mode'            => true
		);
	}

	public function get($var) {
		if(array_key_exists($var, $this->cfg)) {
			return $this->cfg[$var];
		}
		return '';
	}


	/**
	 * Drop any cached config
	 *
	 * @return void
	 */
	public function dropCache() {
		// noop
	}

} 
