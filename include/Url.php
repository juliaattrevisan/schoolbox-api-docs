<?php
/**
 * Formats urls into more meaningful stuff
 */
class Url {

	/**
	 * Creates slugs from a string.
	 * Not sure what the third replacement is supposed to do
	 *
	 * @param  $string
	 * @return string
	 */
	public function convertToURL($string) {
		$pattern[] = '/[^a-zA-Z\d\s\-\+\/]*/';
		$replace[] = '';

		$pattern[] = '/(\s|-|\/)+/';
		$replace[] = '-';

		$pattern[] = '/\+/';
		$replace[] = 'p';

		return strtolower(preg_replace($pattern, $replace, trim($string)));
	}

	/**
	 * Is this a valid url?
	 *
	 * @param  $url
	 * @return bool
	 */
	public function isUrl($url) {
		$cmp = @parse_url($url);

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
	public function isLocalURL($url) {
		list($hostname, $domain) = explode('.',$_SERVER['HTTP_HOST'], 2);

		$pattern = '#^(http(s)?://)('.$hostname.')|('.$_SERVER['HTTP_HOST'].')|('.Cfg::get('site_hostname').'(.'.Cfg::get('site_domain').'))/(.*)?#i';

		return preg_match($pattern, $url);
	}

	/**
	 * Get a relative url variant of $url
	 *
	 * @param  $url
	 * @return mixed
	 */
	public function relativeURL($url) {
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

		return str_replace($hosts, '', $url);
	}
}