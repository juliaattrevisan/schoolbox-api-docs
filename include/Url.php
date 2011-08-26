<?php
/**
 * Formats urls into more meaningful stuff
 */
class Url {
	private $url = '';

	public function __construct($url) {
		$this->setUrl(trim($url));
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
	 * @param  $url
	 * @return int
	 */
	public function isLocalURL() {
		list($hostname, $domain) = explode('.',$_SERVER['HTTP_HOST'], 2);

		$pattern = '#^(http(s)?://)('.$hostname.')|('.$_SERVER['HTTP_HOST'].')|('.Cfg::get('site_hostname').'(.'.Cfg::get('site_domain').'))/(.*)?#i';

		return preg_match($pattern, $this->getUrl());
	}

	/**
	 * Get a relative url variant of $url
	 *
	 * @param  $url
	 * @return mixed
	 */
	public function getRelativeURL() {
		list($hostname, $domain) = explode('.',$_SERVER['HTTP_HOST'],2);

		/* Takes into account subdomain name e.g. ilearn/ and ilearn.joeys
		 * (that originally caused the issue with links not being correct)
		 */
		$hosts = array(
			'http://' . Cfg::get('site_hostname') . '.' . Cfg::get('site_domain'),
			'https://' . Cfg::get('site_hostname'). '.' . Cfg::get('site_domain'),
			'http://' . $_SERVER['HTTP_HOST'],
			'https://' . $_SERVER['HTTP_HOST'],
			'http://' . Cfg::get('site_hostname'),
			'https://' . Cfg::get('site_hostname'),
		);

		return str_replace($hosts, '', $this->getUrl());
	}

	/**
	 * Get a redirect link with base64 encoded url
	 *
	 * @return string
	 */
	public function getBase64DecodedRedirect() {
		return '/redir.php?url=' . base64_encode($this->getUrl());
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
}