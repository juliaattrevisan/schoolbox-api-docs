<?php
/**
 * Url
 *
 * Formats urls into more meaningful stuff.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Url {
	private $url = '';
	private $useCfg = false;

    /**
     * @param $url
     * @param bool $useCfg Decouples the class for unit testing
     */
	public function __construct($url, $useCfg = true) {
		$this->setUrl(trim($url));

		$this->setUseCfg($useCfg);

		if ($this->getUseCfg()) {
			$this->setSiteHostname(Cfg::get('site_hostname'));
			$this->setSiteDomain(Cfg::get('site_domain'));
			$this->setServerHost($_SERVER['HTTP_HOST']);
		}
	}

	/**
	 * Creates slugs from a string.
	 * Not sure what the third replacement is supposed to do
	 *
	 * @param  $string
	 * @return string
	 */
	public function getSlug() {
		$pattern[] = '/[^a-zA-Z\d\s\-\+\/]*/';
		$replace[] = '';

		$pattern[] = '/(\s|-|\/)+/';
		$replace[] = '-';

		$pattern[] = '/\+/';
		$replace[] = 'p';

		return strtolower(preg_replace($pattern, $replace, $this->getUrl()));
	}

	/**
	 * Is this a valid url?
	 *
	 * @param  $url
	 * @return bool
	 */
	public function isUrl() {
		$cmp = @parse_url($this->getUrl());

		if (is_array($cmp) && isset($cmp['host'])){
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the url is for a local page
	 *
	 * @return int
	 */
	public function isLocalURL() {
		list($hostname, $domain) = explode('.', $this->getServerHost(), 2);

		$pattern = '#^(http(s)?://)('.preg_quote($hostname).')|('.preg_quote($this->getServerHost()).')|('.preg_quote($this->getSiteHostname()).'(.'.preg_quote($this->getSiteDomain()).'))/(.*)?#i';

		return preg_match($pattern, $this->getUrl());
	}

	/**
	 * Get a relative url variant of $url
	 *
	 * @param  $url
	 * @return mixed
	 */
	public function getRelativeURL() {
		list($hostname, $domain) = explode('.',$this->getServerHost(),2);

		/* Takes into account subdomain name e.g. ilearn/ and ilearn.joeys
		 * (that originally caused the issue with links not being correct)
		 */
		$hosts = array(
			'http://' . $this->getSiteHostname() . '.' . $this->getSiteDomain(),
			'https://' . $this->getSiteHostname() . '.' . $this->getSiteDomain(),
			'http://' . $this->getServerHost(),
			'https://' . $this->getServerHost(),
			'http://' . $this->getSiteHostname(),
			'https://' . $this->getSiteHostname(),
		);

		return str_replace($hosts, '', $this->getUrl());
	}

	/**
	 * Get a redirect link with base64 encoded url
	 *
	 * @return string
	 */
	public function getBase64EncodedRedirect() {
		return '/redir.php?url=' . base64_encode($this->getUrl());
	}

	/**
	 * Set useCfg. This determines whether to use the Cfg object, or
	 * alternatively whatever input params you want
	 *
	 * @param  $val
	 * @return void
	 */
	public function setUseCfg($val) {
		$this->useCfg = $val;
	}

	/**
	 * Get useCfg
	 *
	 * @return bool
	 */
	private function getUseCfg() {
		return $this->useCfg;
	}

	/**
	 * Set url to work on
	 *
	 * @param  $val
	 * @return void
	 */
	private function setUrl($val) {
		$this->url = $val;
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
	 * Set site hostname
	 *
	 * @param  $val
	 * @return void
	 */
	public function setSiteHostname($val) {
		$this->siteHostname = $val;
	}

	/**
	 * Get site hostname
	 *
	 * @return
	 */
	private function getSiteHostname() {
		return $this->siteHostname;
	}

	/**
	 * Set site domain
	 *
	 * @param  $val
	 * @return void
	 */
	public function setSiteDomain($val) {
		$this->siteDomain = $val;
	}

	/**
	 * Get site domain
	 *
	 * @return
	 */
	private function getSiteDomain() {
		return $this->siteDomain;
	}

	/**
	 * Set server host ($_SERVER['HTTP_HOST'])
	 * @param  $val
	 * @return void
	 */
	public function setServerHost($val) {
		$this->serverHost = $val;
	}

	/**
	 * Get server host
	 *
	 * @return
	 */
	private function getServerHost() {
		return $this->serverHost;
	}
}